<?php

namespace App\Imports;

class ImportBase
{
    protected $file_handle;
    protected $warnings = [];
    protected $records_created_summary = [];

    public function __construct($filename)
    {
        $this->file_handle = fopen($filename, 'r');
        if (!$this->file_handle) {
            throw new \Exception(__('Imports.fopen_fail', ['filename' => $filename]));
        }
    }

    public function __destruct()
    {
        fclose($this->file_handle);
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getRecordsCreatedSummary()
    {
        return $this->records_created_summary;
    }

    /**
     * Clixray CSV files (which actually use '|' as a separator) do not have enclosed fields.
     * That creates an issue when a field contains a new line in it.
     * Lines ending \r\n are indicative of end of row, lines ending just \n are indicative of a
     * new line in the middle of a field.
     *
     * However, files from Clixray which don't support newlines in fields (like the cis-export
     * rcuk_b2c files) don't have \r\n at end of row, just \n, so we have to allow the row
     * terminator to be specified in the function parameters.
     *
     * Because the export files don't meet normal CSV specs, none of the ready built things like fgetcsv,
     * or League\CSV will work with these files.
     */
    protected function fgetClixrayMultilineCsv(
        $csvSeparator = '|',
        $recordTerminator = "\r"
    ) {

        $fields = [];
        $field_num = 0;
        $fields[$field_num] = '';
        $in_field = true;
        $last_char = null;

        while (($char = fgetc($this->file_handle)) !== false) {
            if ($char === $csvSeparator) {
                if ($last_char === $csvSeparator) {  // check for empty field
                    $field_num++;
                    $fields[$field_num] = '';
                    continue;
                }

                $in_field = $in_field ? false : true;
                $field_num++;
                $fields[$field_num] = '';
            } elseif ($char === $recordTerminator) {
                return $fields;
            } elseif ($char === "\n") { // This is, of course, never called if RECORD_TERMINATOR is set to \n
                $fields[$field_num] .= $char;
            } else {
                $fields[$field_num] .= $char;
                $last_char = $char;
            }
        }

        return false;  // EOF
    }

    /**
     * Given an array of values (like the first line of a CSV file with the fieldnames),
     * checks that it does not differ from the expected field names.
     * Throws exception of arrays do not match
     *
     * @param Array $expected_fields - array of field names we expect to see in $actual_fields
     * @param Array $actual_fields   - array of field names to check against $expected_fields
     *
     * @return null
     */
    protected function checkFirstLineFieldsCount(
        array $expected_fields,
        array $actual_fields
    ) {
        if (count(array_diff($expected_fields, $actual_fields))) {
            throw new \Exception(__(
                'Imports.firstline_fail',
                [
                    'expected' => implode(',', $expected_fields),
                    'got' => implode(',', $actual_fields)
                ]
            ));
        }
    }

    /**
     * Given an array of values (like the data line of a CSV file), checks that the
     * count of fields does not differ from the count of the expected fields.
     * Adds element to $this->warnings[] if count does not match
     *
     * @param Array $expected_fields - array of field names we expect to see the corresponding
     *                                 count of in $actual_fields
     * @param Array $actual_fields   - array of field names to check against $expected_fields
     * @param int $record_number     - optional, record number of current record
     *
     * @return boolean
     */
    protected function checkDataLineFieldsCount(
        array $expected_fields,
        array $actual_fields,
        int $record_number = 0
    ) {
        if (count($expected_fields) !== count($actual_fields)) {
            $this->warnings[] = __(
                'Imports.field_count_wrong',
                [
                    'record_number' => $record_number,
                    'expected' => count($expected_fields),
                    'got' => count($actual_fields)
                ]
            );
            return false;
        }

        return true;
    }

    /**
     * Given an email address, tries to extract the persons name,
     * applying capitalization where needed.
     * Assumes firstname.lastname@domain.com format.
     * Also removes any trailing numbers on the name (so bill.smith1@
     * and bill.smith2@ are both named correctly)
     *
     *
     * @param string $email - email address to extract name from
     *
     * @return string - Formatted name on success, email address if it
     *                  doesn't match the required fname.lname@domain.com format
     */
    protected function getNameFromEmailAddress($email)
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (preg_match('/^(.*)\.(.*)@/', $email, $matches)) {
            $first_name = $last_name = '';

            foreach (explode('-', $matches[1]) as $name_element) {
                $first_name .= ucfirst($name_element) . '-';
            }
            foreach (explode('-', $matches[2]) as $name_element) {
                $last_name.= ucfirst($name_element) . '-';
            }
            $first_name = substr($first_name, 0, -1);
            $last_name = substr($last_name, 0, -1);

            $last_name = rtrim($last_name, '0..9');

            return $first_name . ' ' . $last_name;
        }

        return $email;
    }
}
