<?php namespace Kitrix\IB;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyTable;
use CIBlockPropertyEnum;

class Element
{
    /**
     * Get element list with d7 API
     *
     * @param string $tableCode - iblock code
     * @param array $parameters - An array of query parameters, available keys are:
     * <ul>
     * 		<li><b>"select"</b>     => array of fields in the SELECT part of the query, aliases are possible in the form of "alias"=>"field"</li>
     * 		<li><b>"filter"</b>     => array of filters in the WHERE part of the query in the form of "(condition)field"=>"value"</li>
     * 		<li><b>"group"</b>      => array of fields in the GROUP BY part of the query</li>
     * 		<li><b>"order"</b>      => array of fields in the ORDER BY part of the query in the form of "field"=>"asc|desc"</li>
     * 		<li><b>"limit"</b>      => integer indicating maximum number of rows in the selection (like LIMIT n in MySql)</li>
     * 		<li><b>"offset"</b>     => integer indicating first row number in the selection (like LIMIT n, 100 in MySql)</li>
     *		<li><b>"runtime"</b>    => array of entity fields created dynamically</li>
     * 		<li><b>"cache"</b>      => array of cache options
     *          <ul>
     * 			<li>"ttl" => integer indicating cache TTL</li>
     * 			<li>"cache_joins" => boolean enabling to cache joins, false by default</li>
     *          </ul>
     *      </li>
     * </ul>
     * @param bool $loadProps - if true, return also element props (+2 db query)
     * @return array|bool - return array of elements or false (on error)
     * @throws \Exception
     */
    public static function getList(string $tableCode = "", array $parameters = [], bool $loadProps = false)
    {
        // Global filter
        $parameters['filter'] = self::getGlobalFilter($tableCode) + (array)$parameters['filter'];

        // fetch
        $db = ElementTable::getList($parameters);
        if ($db->getSelectedRowsCount() <= 0) {
            return [];
        }

        // get elements
        $elements = [];
        while ($t = $db->fetch()) {
            $elements[$t['ID']] = $t;
        }

        // load props
        if ($loadProps)
        {
            $props = self::getPropsByElementIds( array_keys($elements) );
            foreach ($elements as $element_id => &$element) {
                $element['PROPS'] = $props[$element_id] ?: [];
            }
        }

        // return
        return self::reformatResultCollection($elements);
    }

    /**
     * Get element by ID
     *
     * @param string $tableCode - iblock code
     * @param int $id - element id
     * @param bool $loadProps - if true, return also element props (+2 db query)
     * @return bool|array - return element array or false (if not exist)
     */
    public static function getByID(string $tableCode = "", int $id, bool $loadProps = false)
    {
        // Global filter
        $filter = self::getGlobalFilter($tableCode) + [
            'ID' => $id
        ];

        $collection = self::getList($tableCode, [
            'filter' => $filter
        ], $loadProps);

        if (count($collection) >= 1) {
            return array_shift($collection);
        }

        return false;
    }

    /**
     * Prepare global filter
     *
     * @param $tableCode
     * @return array
     * @throws \Exception
     */
    private static function getGlobalFilter($tableCode)
    {
        if (!$tableCode or $tableCode === "") {
            throw new \Exception("Please provide valid table code");
        }

        return [
            'ACTIVE' => 'Y',
            'IBLOCK.CODE' => $tableCode
        ];
    }

    /**
     * Return props for elements
     *
     * @param $ids
     * @return array
     */
    private static function getPropsByElementIds($ids)
    {
        $ids = array_unique((array)$ids);
        if (count($ids) <= 0) {
            return [];
        }

        // get props
        $db = ElementPropertyTable::getList([
            'filter' => [
                'IBLOCK_ELEMENT_ID' => $ids
            ]
        ]);

        // store prop values
        $propValues = [];

        while ($t = $db->fetch()) {

            $prop_id = $t['IBLOCK_PROPERTY_ID'];
            $prop_element_id = $t['IBLOCK_ELEMENT_ID'];

            $propValues[$prop_id][$prop_element_id][] = $t['VALUE'];
        }

        // normalize props values
        foreach ($propValues as $_id1 => $props)
        {
            foreach ($props as $_id2 => $values)
            {
                if (count($values) == 1)
                {
                    $values = array_shift($values);
                }

                $propValues[$_id1][$_id2] = $values;
            }
        }

        // get prop codes
        $propsById = [];
        $propIds = array_unique(array_keys($propValues));

        if (count($propIds))
        {
            $db = PropertyTable::getList([
                'filter' => [
                    'ID' => $propIds
                ]
            ]);

            $enumBlockIds = [];
            while ($t = $db->fetch())
            {

                $code = strtolower($t['CODE']);
                $prop_id = $t['ID'];
                $values = $propValues[$prop_id];

                if ($t['PROPERTY_TYPE'] == 'L') {
                    $enumBlockIds[] = $t['IBLOCK_ID'];
                }

                foreach ($values as $element_id => $value) {
                    $propsById[$element_id][$code] = $value;
                }
            }

            if (count($enumBlockIds) >= 1)
            {
                $enum_db = CIBlockPropertyEnum::GetList([], [
                    'IBLOCK_ID' => $enumBlockIds
                ]);

                $enums = [];
                while ($t = $enum_db->Fetch()) {
                    $enums[strtolower($t['PROPERTY_CODE'])][$t['ID']] = $t['VALUE'];
                }

                // re assign prop values
                foreach ($propsById as $prod_id => $fields)
                {
                    foreach ($fields as $code => $value)
                    {
                        if (in_array($code, array_keys($enums)))
                        {
                            $newValue = $enums[$code][$value];
                            $propsById[$prod_id][$code] = $newValue;
                        }
                    }
                }
            }
        }

        return $propsById;
    }

    /**
     * Prepare final collection of elements to output
     *
     * @param array $elements - list of elements
     * @return array
     */
    private static function reformatResultCollection(array $elements)
    {
        $collection = [];

        // 1. lower string element props && blacklisting
        $propsModifyMap = [
            'timestamp_x' => 'modified',
            'date_create' => 'created',
            'iblock_section_id' => 'section_id',
            'name' => 'title',
            'searchable_content' => false,
            'wf_status_id' => false,
            'wf_parent_element_id' => false,
            'wf_new' => false,
            'wf_locked_by' => false,
            'wf_date_lock' => false,
            'wf_comments' => false,
            'show_counter' => false,
            'show_counter_start' => false,
        ];

        array_walk($elements, function($row, $element_id) use (&$collection, $propsModifyMap) {

            $elementRows = [
                '_index' => $row['ID']
            ];

            foreach ($row as $code => $val) {

                $code = strtolower($code);

                if (in_array($code, array_keys($propsModifyMap))) {

                    $code = $propsModifyMap[$code];
                    if (!$code) {
                        continue;
                    }
                }

                $elementRows[$code] = $val;
            }

            $collection[$element_id] = $elementRows;
        });

        // result
        return $collection;
    }
}
