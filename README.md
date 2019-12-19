# PHP CSV File Iterator
PHP class for low memory processing large CSV files with data processing and data naming automation.

## Usage
Reading data out of a CSV file:
```php
use KRP\FileSystem\CsvFileIterator;

$pathToFile = '/path/to/file.csv';
$rows = new CsvFileIterator($pathToFile, $delimiter = ';', $fieldEnclosure = '"', $escapeChar = '\\');

// Set value filter for values processing (e.g. formatting, stripping whitespaces, type casting etc.)
$rows->setValueFilter(function ($value, $context) {
        var_dump($context); // Prints array ['row' => row_number, 'column' => column_name] to find out the value context if needed
        return \clean_string($value); // Example of value processing
    });

// Use the first row values as column names
$rows->useFirstRowAsHeader();

// ... or map your own names for the first row values as column names
$columns_map = [
    'Shimanoitem' => 'code',
    'barcode/ean' => 'ean',
    'description line 1' => 'description1',
    'description line 2' => 'description2',
    'T' => 'tax',
    'Price' => 'price',
    'SalesPr' => 'price_sales',
    'Retail' => 'price_retail',
    'S' => 'stock',
    'Brand' => 'brand',
    'Filename picture' => 'image',
    'A' => 'available'
];
$rows->useFirstRowAsHeader($columns_map);

// ... or set your own column names if file hasn't got its own header
$columns = ['item_number', 'code', '3rd_item_number', 'ean', 'description1', 'description2', 'tax', 'price', 'price_sales', 'price_retail', 'cnv', 'stock', 'item_group', 'brand', 'image', 'saq', 'available'];
$rows->setColumnNames($columns);

// Get current column names
var_dump($rows->getColumnNames());

// Get lines/rows count (skiping header if exists)
var_dump($rows->count());

// Iterate throughout the file
foreach ($rows as $row) {
    var_dump($row);
    $i++; if ($i == 2) break;
}
```
