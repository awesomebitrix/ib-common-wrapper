# ib-common-wrapper
Kitrix common info block wrapper d7

## setup
```php
$ composer require kitrix/ib
```

## use
```php
// all active elements from IB slider - (without props)
Kitrix\IB\Element::getList('slider');

// active elements from IB slider with ID IN (1,2,3) - (without props)
Kitrix\IB\Element::getList('slider', [
    'filter' => [
        'ID' => [1,2,3]
    ]
]);

// all active elements from IB slider WITH props
Kitrix\IB\Element::getList('slider', [], true);

// element with ID 200 from IB catalog
Kitrix\IB\Element::getByID('catalog', 200);
```

## return
Method return list of element array:
```php
Array
(
    [_index] => 33
    [id] => 33
    [modified] => Bitrix\Main\Type\DateTime Object
        (
            [value:protected] => DateTime Object
                (
                    [date] => 2017-03-10 13:13:49.000000
                    [timezone_type] => 3
                    [timezone] => Europe/Moscow
                )

        )

    [modified_by] => 1
    [created] => Bitrix\Main\Type\DateTime Object
        (
            [value:protected] => DateTime Object
                (
                    [date] => 2017-02-22 17:37:47.000000
                    [timezone_type] => 3
                    [timezone] => Europe/Moscow
                )

        )

    [created_by] => 1
    [iblock_id] => 5
    [section_id] => 
    [active] => Y
    [active_from] => 
    [active_to] => 
    [sort] => 200
    [title] => elementTitle
    [preview_picture] => 
    [preview_text] => 
    [preview_text_type] => text
    [detail_picture] => 55
    [detail_text] => 
    [detail_text_type] => text
    [in_sections] => N
    [xml_id] => 33
    [code] => 
    [tags] => 
    [tmp_id] => 0
    [props] => Array
    (
        [title] => some example prop
        [link_to] => /company/about/
        [link_title] => read more
    )
)
```
