<?php
namespace KRP\FileSystem;

class CsvFileIterator extends \SplFileObject
{

    /**
     * @var bool
     */
    private $firstRowUsedAsColumnNames = FALSE;

    /**
     * @var array
     */
    private $columnNames = [];

    /**
     * @var \Closure
     */
    private $valueFilter = NULL;

    /**
     * Construct a new file object.
     *
     * @param string $fileName The file to read.
     * @param string $delimiter The field delimiter (one character only). Defaults as a comma.
     * @param string $fieldEnclosure The field enclosure character (one character only). Defaults as a double quotation mark.
     * @param string $escapeChar The escape character (at most one character). Defaults as a backslash (\). An empty string ("") disables the proprietary escape mechanism.
     * @throws RuntimeException If the filename cannot be opened.
     * @throws LogicException If the filename is a directory.
     * @return void
     */
    public function __construct($fileName, $delimiter = ',', $fieldEnclosure = '"', $escapeChar = '\\')
    {
        parent::__construct($fileName, 'r');
        $this->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $this->setCsvControl($delimiter, $fieldEnclosure, $escapeChar);
    }

    /**
     * Set callback function for values' filtering
     *
     * @param \Clousure $callback An anonymous function that processes values (for purposes like: formatting, cleaning etc.)
     * @return void
     */
    public function setValueFilter($callback)
    {
        $this->valueFilter = ($callback instanceof \Closure) ? $callback : NULL;
    }

    /**
     * Apply filters to a row fields
     *
     * @param array $row Array of values in a row
     * @return array A row with filtered values
     */
    private function applyFilters($row)
    {
        if ($filter = $this->valueFilter) {
            foreach ($row as $column => &$value) {
                $value = $filter($value, $context = ['row' => $this->key(), 'column' => $column]);
            }
        }
        return $row;
    }

    /**
     * Get columns' names
     *
     * @return array
     */
    public function getColumnNames()
    {
        return $this->columnNames;
    }

    /**
     * Set names for columns
     *
     * @param array $names One dimentional array with column names.
     * @return CsvFileIterator
     */
    public function setColumnNames(array $columnNames = [])
    {
        if (!is_array($columnNames) || empty($columnNames)) return $this;
        
        // Correct empty names
        foreach ($columnNames as $key => &$value) if ($value === '') $value = "COL_{$key}";

        // Correct duplicates
        $duplicates = array_diff_key($columnNames, array_unique($columnNames, \SORT_STRING));
        if (!empty($duplicates)) {
            foreach ($duplicates as $key => &$value) $value = "{$value}_{$key}";
            $columnNames = array_replace($columnNames, $duplicates);
        }
        
        $this->columnNames = $columnNames;
        
        return $this;
    }

    /**
     * Use the values from the first row as the keys for the remaining rows.
     *
     * @param array $namesMap (optional) Associative array with ncolumn names replacement like ['column_name_in_file' => 'new_column_name']
     * @return CsvFileIterator
     */
    public function useFirstRowAsHeader(array $namesMap = [])
    {
        
        // Rewind the file to the first line if needed
        $currentLine = $this->key();
        if ($currentLine > 0) $this->rewind();
        
        // Get the first line
        $columnNames = $this->applyFilters(parent::current());
        
        // Map column names to the new ones if provided
        if (is_array($namesMap) && !empty($namesMap)) {
            foreach ($columnNames as &$columnName) {
                if ($columnName && isset($namesMap[$columnName])) $columnName = $namesMap[$columnName];
            }
        }
        
        $this->setColumnNames($columnNames);
        $this->firstRowUsedAsColumnNames = true;

        // Set file pointer back to the current line
        if ($currentLine > 0) $this->seek($currentLine);

        return $this;
    }

    /**
     * Current CSV row.
     *
     * @return array Two dimensional array as [column_name => value]
     */
    public function current()
    {
        // Skip the first row if column names are set by the first row.
        if ($this->key() == 0 && $this->firstRowUsedAsColumnNames) $this->next();

        $rowValues = parent::current();
        if (!$rowValues) return [];        
        
        $columnNames = $this->columnNames;
        $columnsLength = count($columnNames);
        $rowLength = count($rowValues);
        
        // Check the header and row size match.
        if ($columnsLength > $rowLength) {
            // If there's more column names than data, pad out the data with nulls to match columns width.
            $rowValues = array_pad($rowValues, count($this->columnNames), NULL);
        } else if ($columnsLength < $rowLength) {
            // If there's more data than column names add missing columns
            while ($columnsLength < $rowLength) {
                $columnNames[] = "COL_{$columnsLength}";
                $columnsLength++;
            }
        }

        // Combine the data with the column names as keys.
        $rowValues = array_combine($columnNames, $rowValues);
        
        // Apply values' filters
        $rowValues = $this->applyFilters($rowValues);
        
        return $rowValues;
    }

    /**
     * Get lines/rows count excluding header line/row if set.
     *
     * @return integer Lines/rows count.
     */
    public function count()
    {
        // Rewind the file to the first line if needed
        $currentLine = $this->key();
        if ($currentLine > 0) $this->rewind();
        
        // Skip header
        if ($this->firstRowUsedAsColumnNames) $this->next();
        
        // Count lines
        $count = 0;
        while (!$this->eof()) {
            $count++;
            $this->next();
        }
        
        // Set file pointer back to the current line
        if ($currentLine != $this->key()) $this->seek($currentLine);

        return $count;
    }
}
