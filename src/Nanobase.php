<?php

/**
 * Nanobase is a fast, lightweight relational database PHP class.
 * PHP Version 8.0.8.
 *
 * @see https://github.com/sheldonkennedy/nanobase/
 *
 * @author    Sheldon Kennedy (sheldonkennedy@gmail.com)
 * @copyright 2022 Sheldon Kennedy
 * @version   0.2.4
 *
 * This program is distributed without any warranty or the implied warranty of fitness for a
 * particular purpose.
 */

class Nanobase {

    /**
     * The path to the table folder.
     *
     * @var string
     */
    public $tablePath;

    /**
     * The path to the table digest file.
     *
     * @var string
     */
    public $digestPath = null;

    /**
     * Details of each column found in the digest file.
     *
     * @var array
     */
    public $digest = [];

    /**
     * Contains an SPL file object for each digest column to be searched.
     *
     * @var array
     */
    public $columns = [];

    /**
     * What to return if an exception is thrown.
     *
     * true:  return a JSON message.
     * false: return false.
     *
     * @var bool
     */
    public $isReport = true;

    /**
     * Column details from the digest to be used during search.
     *
     * @var array
     */
    public $searchColumns = [];

    /**
     * Column details from the digest to be used during operations.
     *
     * @var array
     */
    public $operationColumns = [];

    /**
     * Maximum quantity of records to search through.
     *
     * @var int
     */
    public $limit = 1;

    /**
     * Whether to match a full or partial search phrase.
     *
     * true:  Search for the entire phrase.
     * false: Perform a substring search within entries for the phrase.
     *
     * @var bool
     */
    public $isWhole = false;

    /**
     * Case sensitivity of the search phrase.
     *
     * true:  Perform a case-sensitive search.
     * false: Perform a case-insensitive search.
     *
     * @var bool
     */
    public $isCase = false;

    /**
     * Contains the record keys of entries found during search.
     *
     * @var array
     */
    public $keys = [];

    /**
     * Contains the column file start positions of entries found during search.
     *
     * @var array
     */
    public $positions = [];

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Constructor.
     *
     * @param string      $databasePath Path to the database folder
     * @param string|null $tableName    Name of the table to be used or made
     */
    function __construct(string $databasePath, string $tableName = null) {

        $databasePath = trim($databasePath);
        $tableName    = trim($tableName);

        /**
         * Standardise the URL with a trailing backslash
         */
        if (!str_ends_with($databasePath, '/')) $databasePath .= '/';

        $tablePath = $databasePath . $tableName;

        /**
         * Standardise the URL with a trailing backslash
         */
        if (!str_ends_with($tablePath, '/')) $tablePath .= '/';

        $this->databasePath = $databasePath;
        $this->tableName    = $tableName;
        $this->tablePath    = $tablePath;
        $this->digestPath   = $tablePath . 'digest.json';
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Unlock all columns when the object is destroyed.
     */
    function __destruct() {

        if ($this->columns) foreach ($this->columns as $column) $column->flock(LOCK_UN);
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Load the table digest from file.
     *
     * @promotes $digest
     *
     * @throws Exception If a table folder is not found or is not a folder
     * @throws Exception If a table folder is not writeable
     * @throws Exception If the digest is not found
     *
     * @return false|void
     */
    private function loadTable() {

        try {

            $digest = [];

            if (!$this->tablePath || !is_dir($this->tablePath)):

                throw new Exception('Table could not be found', 404);

            endif;

            if (!is_writeable($this->tablePath)):

                throw new Exception('Table is not writeable', 400);

            endif;

            if (!file_exists($this->digestPath)) throw new Exception('Digest is missing', 400);

            $digest = json_decode(file_get_contents($this->digestPath), true);

            $this->digest = $digest;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Make a separate SPL file object to represent each digest column and lock each object
     * resource.
     *
     * @promotes $columns
     *
     * @throws Exception If a table has no columns
     * @throws Exception If a column has no corresponding file
     *
     * @return false|void
     */
    private function loadColumns() {

        try {

            if (!$this->digest) throw new Exception('Table has no columns', 404);

            $columnPath = null;

            foreach ($this->digest as $columnDetails):

                $columnPath = $this->tablePath . $columnDetails['name'] . '.db';

                if (!is_file($columnPath)):

                    throw new Exception('Digest contains column with no file', 404);

                endif;

            endforeach;

            foreach ($this->digest as $columnDetails):

                $columnPath = $this->tablePath . $columnDetails['name'] . '.db';

                $column = new SplFileObject($columnPath, 'r+');

                $this->columns[$columnDetails['name']] = $column;

            endforeach;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Group all digest columns to search through.
     *
     * @param array $operationColumns Names of the columns to search through
     *
     * @promotes $searchColumns
     *
     * @throws Exception If a provided column is not found in the digest
     *
     * @return false|void
     */
    private function loadSearchColumns(array $searchColumns = []) {

        try {

            /**
             * Load only the selected columns.
             */
            if ($searchColumns):

                $tempDigest = [];

                /**
                 * Create a temp digest containing only those search columns that exist in the
                 * digest.
                 */
                foreach ($searchColumns as $columnName):

                    if (in_array($columnName, array_column($this->digest, 'name'))):

                        $key = array_search(
                            $columnName,
                            array_column(
                                $this->digest,
                                'name'),
                            true
                        );

                        $tempDigest[] = $this->digest[$key];

                    else:

                        throw new Exception('Column could not be found in digest', 404);

                    endif;

                endforeach;

            /**
             * Load all the columns.
             */
            else:

                $tempDigest = $this->digest;

            endif;

            $this->searchColumns = $tempDigest;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Group all selected digest columns to use in operations.
     *
     * @param array $operationColumns Names of the columns to operate on
     *
     * @promotes $operationColumns
     *
     * @throws Exception If a provided column is not found in the digest
     *
     * @return false|void
     */
    private function loadOperationColumns(array $operationColumns = []) {

        try {

            /**
             * Load the selected columns.
             */
            if ($operationColumns):

                $tempDigest = [];

                foreach ($operationColumns as $columnName):

                    if (in_array($columnName, array_column($this->digest, 'name'))):

                        $key = array_search(
                            $columnName,
                            array_column(
                                $this->digest,
                                'name'),
                            true
                        );

                        $tempDigest[] = $this->digest[$key];

                    else:

                        throw new Exception(
                            'Selected column could not be found in digest',
                            404
                        );

                    endif;

                endforeach;

            /**
             * Load all the columns.
             */
            else:

                $tempDigest = $this->digest;

            endif;

            $this->operationColumns = $tempDigest;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Search through the entries to get their corresponding record key.
     *
     * @param array|null $term String to search for
     *
     * @promotes $keys
     *
     * @throws Exception If a reserved character is provided
     * @throws Exception If no key is found
     *
     * @return false|void
     */
    private function loadKeys(string $term = null) {

        try {

            /**
             * Find the record keys that correspond with the term.
             */
            if ($term):

                $term = trim($term);

                if (str_contains($term, '|') || str_contains($term, '_')):

                    throw new Exception('Entry contains a reserved character', 400);

                endif;

                $count = 0;

                foreach ($this->searchColumns as $column):

                    $columnName = null;
                    $columnPath = null;
                    $capacity   = null;
                    $file       = null;

                    $columnName = $column['name'];
                    $columnPath = $this->tablePath . $columnName. '.db';
                    $capacity   = $column['capacity'];

                    /**
                     * Search parameters.
                     */
                    $offset = - 2;
                    $match  = false;

                    $file = $this->columns[$columnName];
                    $file->rewind();

                    while ($file->valid() && $count < $this->limit):

                        $file->fseek($offset + 11, SEEK_CUR);
                        $readEntry = $file->fread($capacity);

                        if ($this->whole):

                            /**
                             * Compress the search term for a faster string comparison.
                             */
                            $newEntry = $this->compress($term, $capacity);

                            if (!$this->isCase):

                                if (mb_strtolower($readEntry) === mb_strtolower($newEntry)):

                                    $match = true;

                                endif;

                            else:

                                if ($readEntry === $newEntry):

                                    $match = true;

                                endif;

                            endif;

                        else:

                            if (!$this->isCase):

                                if (
                                    str_contains(
                                        mb_strtolower($readEntry),
                                        mb_strtolower($term)
                                    )
                                ):

                                    $match = true;

                                endif;

                            else:

                                if (str_contains($readEntry, $term)):

                                    $match = true;

                                endif;

                            endif;

                        endif;

                        if ($match):

                            $count ++;

                            $file->fseek(- $capacity - 9, SEEK_CUR);
                            $this->keys[] = $file->fread(8);

                            /**
                             * Use fread in place of fseek.
                             */
                            $file->fread($capacity + 1);

                        endif;

                        $offset = 0;
                        $match  = false;

                    endwhile;

                endforeach;

            /**
             * Find all the record keys.
             */
            else:

                $count = 0;

                foreach ($this->searchColumns as $column):

                    $columnName = null;
                    $columnPath = null;
                    $capacity   = null;
                    $file       = null;

                    $columnName = $column['name'];
                    $columnPath = $this->tablePath . $columnName. '.db';
                    $capacity   = $column['capacity'];

                    /**
                     * Search parameter.
                     */
                    $offset = - $capacity - 11;

                    $file = $this->columns[$columnName];
                    $file->rewind();

                    while ($file->valid() && $count < $this->limit):

                        $count ++;

                        $file->fseek($offset + $capacity + 3, SEEK_CUR);
                        $this->keys[] = $file->fread(8);

                        /**
                         * Reset the search parameter for the next iteration.
                         */
                        $offset = 0;

                    endwhile;

                endforeach;

            endif;

            if (!$this->keys) throw new Exception('Record was not found', 404);

            $this->keys = array_unique($this->keys, SORT_REGULAR);

            /**
             * Remove empty array elements.
             */
            foreach ($this->keys as $key => $value) if (!$value) unset($this->keys[$key]);

            /**
             * Reduce the keys to the limit.
             */
            if (count($this->keys) > $this->limit) array_pop($this->keys);

            /**
             * Renumber the array.
             */
            $this->keys = array_values($this->keys);

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Use the keys to search for the starting position of each entry.
     *
     * @promotes $positions
     *
     * @return void
     */
    private function loadPositions(): void {

        $columnNames = array_column($this->digest, 'name') ?? [];

        foreach ($columnNames as $columnName):

            // $columnPath = $this->tablePath . $columnName . '.db';
            $digestKey = array_search($columnName, array_column($this->digest, 'name'));
            $capacity  = $this->digest[$digestKey]['capacity'];

            $file = $this->columns[$columnName] ?? null;
            $file->rewind();

            /**
             * Search parameter.
             */
            $offset = - $capacity - 8;

            while ($file->valid()):

                $file->fseek($offset + $capacity + 3, SEEK_CUR);
                $readKey = $file->fread(8);

                if (in_array($readKey, $this->keys)):

                    $position = $file->ftell() + 1;

                    $this->positions[$columnName][] = [
                        'key'      => $readKey,
                        'position' => $position
                    ];

                endif;

                /**
                 * Reset the search parameter for the next iteration.
                 */
                $offset = 0;

            endwhile;

        endforeach;
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Use the starting positions to get the full record for each key.
     *
     * @promotes $entries
     *
     * @return false|void
     */
    private function loadEntries() {

        foreach ($this->digest as $digestColumn):

            $columnName      = null;
            $columnPath      = null;
            $digestKey       = null;
            $capacity        = null;
            $columnPositions = [];

            $columnName      = $digestColumn['name'];
            $columnPath      = $this->tablePath . $columnName . '.db';
            $digestKey       = array_search($columnName, array_column($this->digest, 'name'));
            $capacity        = $this->digest[$digestKey]['capacity'];
            $columnPositions = $this->positions[$columnName];

            foreach ($columnPositions as $columnPosition):

                $key      = null;
                $position = null;
                $file     = null;

                $key      = $columnPosition['key'];
                $position = $columnPosition['position'];

                $file = $this->columns[$columnName];
                $file->fseek($position);
                $readEntry = $file->fread($capacity);

                /**
                 * Remove trailing empty space characters.
                 */
                $readEntry = rtrim($readEntry, '_');

                /**
                 * If the entry is a list then explode it into an array.
                 */
                if (str_contains($readEntry, '|')) $readEntry = explode('|', $readEntry);

                $this->entries[$key][$columnName] = $readEntry;

            endforeach;

        endforeach;
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Choose whether to return a JSON message or false when an exception is thrown.
     *
     * @param bool @exceptions True returns a message
     *
     * @promotes $isReport
     *
     * @return void
     */
    function throw(bool $isReport): void {

        $this->isReport = $isReport;
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Perform search.
     *
     * @param string|null @term    Search term
     * @param array       @columns Columns name to search through
     * @param int         @limit   Maximum quantity of records to search
     * @param bool|false  @whole   Match the search term to the entire or partial column term
     * @param bool|false  @case    Case-sensitive or case-insensitive search
     *
     * @promotes $limit
     * @promotes $isWhole
     * @promotes $isCase
     *
     * @return self
     */
    function search(
        string $term    = null,
        array  $columns = [],
        int    $limit   = 1,
        bool   $isWhole = false,
        bool   $isCase  = false
    ): self {

        if ($this->tablePath):

            if ($limit < 1):

                $this->limit = 1;

            elseif ($limit > 100):

                $this->limit = 100;

            else:

                $this->limit = $limit;

            endif;

            $this->whole = $isWhole;
            $this->isCase  = $isCase;

            $this->loadTable();
            $this->loadColumns();
            $this->loadSearchColumns($columns);
            $this->loadKeys($term);
            $this->loadPositions();

        endif;

        return $this;
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Return a single record with no parent key.
     *
     * @throws Exception If a record is not found
     *
     * @return array|null
     */
    function read(): array|false {

        try {

            if (!$this->positions) throw new Exception('Record could not be found', 404);

            $this->loadEntries();

            if (!$this->entries) return false;

            foreach ($this->entries as $entry) return $entry;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Return records with parent keys.
     *
     * @throws Exception If a record is not found
     *
     * @return array|null
     */
    function list(string $entry = null): array|false {

        try {

            if (!$this->positions) throw new Exception('Record could not be found', 404);

            $this->loadEntries();

            if (!$this->entries) return false;

            return $this->entries;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Make a new record. All column entries are checked first and an exception will be thrown
     * before anything is written to any column.
     *
     * @param array $newEntries New data to insert into the columns
     *
     * @throws Exception If the provided entry is not safe
     * @throws Exception If the provided entry contains a reserved character
     * @throws Exception If the table has reached its key limit
     * @throws Exception If an entry exceeds the remaining character length
     * @throws Exception If an entry is not written to file
     *
     * @return bool
     */
    function make(array $newEntries): bool {

        try {

            if (!$this->isSafe($newEntries)):

                throw new Exception('Entry contains potentially harmful characters', 400);

            endif;

            foreach ($newEntries as $newEntry):

                if (str_contains($newEntry, '|') || str_contains($newEntry, '_')):

                    throw new Exception('Entry contains a reserved character', 400);

                endif;

            endforeach;

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            $this->loadTable();
            $this->loadColumns();
            $this->loadOperationColumns(array_keys($newEntries));

            $allKeys    = [];
            $columnName = null;
            $columnPath = null;
            $offset     = null;
            $capacity   = null;
            $file       = null;
            $key        = null;

            foreach ($this->operationColumns as $operationColumn):

                $columnName = $operationColumn['name'];
                $columnPath = $this->tablePath . $columnName . '.db';
                $offset     = - $operationColumn['capacity'] - 3;
                $capacity   = $operationColumn['capacity'];

                $file = $this->columns[$columnName];
                $file->rewind();

                while ($file->valid()):

                    $file->fseek($offset + $capacity + 3, SEEK_CUR);
                    $key = $file->fread(8);

                    $allKeys[] = (int) $key;

                    /**
                     * Reset the search parameter.
                     */
                    $offset = 0;

                endwhile;

            endforeach;

            /**
             * Get the highest existing key accross all columns and create a new key.
             */
            $key = max($allKeys) + 1;

            /**
             * Check the new key does not exceed the allowed character length.
             */
            if ($key > 99999999) throw new Exception('Table has reached its key limit', 400);

            /**
             * Pad the new key with leading zeros.
             */
            while (strlen($key) < 8) $key = '0' . $key;

            $columnName = null;
            $columnPath = null;
            $capacity   = null;
            $file       = null;

            /**
             * Check every column entry is eligible for the operation.
             */
            foreach ($this->operationColumns as $operationColumn):

                $columnName = $operationColumn['name'];
                $capacity   = $operationColumn['capacity'];
                $newEntry   = $newEntries[$columnName];

                $compressedEntry = $this->compress($newEntry, $capacity);

                if (!$compressedEntry):

                    throw new Exception(
                        'Entry is too long for column ' . $columnName,
                        400
                    );

                endif;

            endforeach;

            $columnName = null;
            $columnPath = null;
            $capacity   = null;
            $file       = null;

            /**
             * Commit the operation.
             */
            foreach ($this->operationColumns as $operationColumn):

                $columnName = $operationColumn['name'];
                $columnPath = $this->tablePath . $columnName . '.db';
                $capacity   = $operationColumn['capacity'];
                $newEntry   = $newEntries[$columnName];

                $compressedEntry = $this->compress($newEntry, $capacity);

                $fullEntry    = $key . '|' . $compressedEntry . "\r\n";
                $fullCapacity = $capacity + 11;

                $file = $this->columns[$columnName];
                $file->fseek(0, SEEK_END);
                $isWrite = $file->fwrite($fullEntry, $fullCapacity);

            endforeach;

            if (!$isWrite) throw new Exception('Entry could not be made', 500);

            return true;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * A SEARCH MUST BE RUN FIRST.
     *
     * Overwrite an existing column entry, or append a new entry to a column using the same key if
     * there is no matching entry in that column.
     *
     * @param string     $newEntry         New entry to replace the column entry
     * @param array|null $operationColumns Names of the columns to affect
     *
     * @throws Exception If the provided entry is not safe
     * @throws Exception If the provided entry contains a reserved character
     * @throws Exception If an entry position is not found
     * @throws Exception If the entry exceeds the remaining character length
     * @throws Exception If the entry is not written to file
     *
     * @return bool
     */
    function update(string $newEntry, array $operationColumns = []): bool {

        try {

            if (!$this->isSafe($newEntry)):

                throw new Exception('Entry contains potentially harmful characters', 400);

            endif;

            if (str_contains($newEntry, '|') || str_contains($newEntry, '_')):

                throw new Exception('Entry contains a reserved character', 400);

            endif;

            if (!$this->positions) throw new Exception('Record could not be found', 404);

            $this->loadOperationColumns($operationColumns);

            $columnName      = null;
            $columnPath      = null;
            $capacity        = null;
            $columnPositions = [];

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            /**
             * Check every column entry is eligible for the operation before committing.
             */
            foreach ($this->operationColumns as $operationColumn):

                $capacity        = $operationColumn['capacity'];
                $compressedEntry = $this->compress($newEntry, $capacity);

                if (!$compressedEntry) throw new Exception('Entry is too long for column', 400);

            endforeach;

            $key      = null;
            $position = null;
            $file     = null;

            /**
             * Commit operation.
             */
            foreach ($this->operationColumns as $operationColumn):

                $columnName      = $operationColumn['name'];
                $columnPath      = $this->tablePath . $columnName . '.db';
                $capacity        = $operationColumn['capacity'];
                $columnPositions = $this->positions[$columnName];
                $compressedEntry = $this->compress($newEntry, $capacity);

                foreach ($columnPositions as $columnPosition):

                    $key      = $columnPosition['key'];
                    $position = $columnPosition['position'];

                    $file = $this->columns[$columnName];
                    $file->rewind();

                    /**
                     * If the entry already exists then overwrite in place.
                     */
                    if ($position):

                        $file->fseek($position);
                        $isWrite = $file->fwrite($compressedEntry, $capacity);

                    /**
                     * If the entry does not exist then append key and entry to table.
                     */
                    else:

                        $fullEntry    = $key . '|' . $compressedEntry . "\r\n";
                        $fullCapacity = $capacity + 11;

                        $file->fseek(0, SEEK_END);
                        $isWrite = $file->fwrite($fullEntry, $fullCapacity);

                    endif;

                endforeach;

            endforeach;

            if (!$isWrite) throw new Exception('Entry could not be written', 500);

            return true;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * A SEARCH MUST BE RUN FIRST.
     *
     * Add a list item to an existing column entry. The entry will be converted into a list if
     * it is not one already.
     *
     * @param string     $newItem          New list item to append to the column entry
     * @param array|null $operationColumns Names of the columns to affect
     *
     * @throws Exception If the provided item is not safe
     * @throws Exception If the provided item contains a reserved character
     * @throws Exception If an entry position is not found
     * @throws Exception If the item exceeds the remaining character length
     * @throws Exception If the item is not written to file
     *
     * @return bool
     */
    function attach(string $newItem, array $operationColumns = []): bool {

        try {

            if (!$this->positions) throw new Exception('Record could not be found', 404);

            if (!$this->isSafe($newItem)):

                throw new Exception('Entry contains potentially harmful characters', 400);

            endif;

            if (str_contains($newItem, '|') || str_contains($newItem, '_')):

                throw new Exception('Entry contains a reserved character', 400);

            endif;

            $this->loadOperationColumns($operationColumns);

            $columnName      = null;
            $columnPath      = null;
            $capacity        = null;
            $columnPositions = [];
            $key             = null;
            $position        = null;
            $file            = null;

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            foreach ($this->operationColumns as $operationColumn):

                $columnName      = $operationColumn['name'];
                $columnPath      = $this->tablePath . $columnName . '.db';
                $capacity        = $operationColumn['capacity'];
                $columnPositions = $this->positions[$columnName];

                /**
                 * Check every column entry is eligible for the operation.
                 */
                foreach ($columnPositions as $columnPosition):

                    $key      = $columnPosition['key'];
                    $position = $columnPosition['position'];

                    $file = $this->columns[$columnName];
                    $file->rewind();
                    $file->fseek($position);

                    /**
                     * If the column entry exists.
                     */
                    if ($position):

                        $readEntry = rtrim($file->fread($capacity), '_');

                        /**
                         * If an entry already exists then add the list delimiter and compress the
                         * entry.
                         */
                        if ($readEntry):

                            $compressedEntry = $this->compress(
                                $readEntry . '|' . $newItem,
                                $capacity
                            );

                        /**
                         * If there is no existing entry then compress the entry without the list
                         * delimiter.
                         */
                        else:

                            $compressedEntry = $this->compress($newItem, $capacity);

                        endif;

                        if (!$compressedEntry):

                            throw new Exception('Entry is too long for column', 400);

                        endif;

                    endif;

                endforeach;

                /**
                 * Commit the operation.
                 */
                foreach ($columnPositions as $columnPosition):

                    $key      = $columnPosition['key'];
                    $position = $columnPosition['position'];

                    $file = $this->columns[$columnName];
                    $file->rewind();
                    $file->fseek($position);

                    /**
                     * If the column entry exists.
                     */
                    if ($position):

                        $readEntry = rtrim($file->fread($capacity), '_');

                        /**
                         * If an entry already exists then add the list delimiter and compress the
                         * entry.
                         */
                        if ($readEntry):

                            $compressedEntry = $this->compress(
                                $readEntry . '|' . $newItem,
                                $capacity
                            );

                        /**
                         * If there is no existing entry then compress the entry without the list
                         * delimiter.
                         */
                        else:

                            $compressedEntry = $this->compress($newItem, $capacity);

                        endif;

                        /**
                         * Overwrite the existing column entry.
                         */
                        $file->fseek($position);
                        $isWrite = $file->fwrite($compressedEntry, $capacity);

                    /**
                     * If a column entry does not exist then append the key and entry to the
                     * column.
                     */
                    else:

                        $compressedEntry = $this->compress($newItem, $capacity);
                        $fullEntry       = $key . '|' . $compressedEntry . "\r\n";
                        $fullCapacity    = $capacity + 11;

                        $file->fseek(0, SEEK_END);
                        $isWrite = $file->fwrite($fullEntry, $fullCapacity);

                    endif;

                endforeach;

            endforeach;

            if (!$isWrite) throw new Exception('Entry could not be appended', 500);

            return true;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * A SEARCH MUST BE RUN FIRST.
     *
     * Remove a list item from an existing column entry. The column entry must be a list for this
     * to work.
     *
     * @param string     $detachItem       List item to detach from column entry
     * @param array|null $operationColumns Names of the columns to affect
     *
     * @throws Exception If the provided item is not safe
     * @throws Exception If the provided item contains a reserved character
     * @throws Exception If an entry position is not found
     * @throws Exception If the item exceeds the remaining character length
     * @throws Exception If the item is not written to file
     *
     * @return bool
     */
    function detach(string $detachItem, array $operationColumns = []): bool {

        try {

            if (!$this->isSafe($detachItem)):

                throw new Exception('Entry contains potentially harmful characters', 400);

            endif;

            if (str_contains($detachItem, '|') || str_contains($detachItem, '_')):

                throw new Exception('Entry contains a reserved character', 400);

            endif;

            if (!$this->positions) throw new Exception('Record could not be found', 404);

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            $this->loadOperationColumns($operationColumns);

            $columnName      = null;
            $columnPath      = null;
            $capacity        = null;
            $columnPositions = [];
            $key             = null;
            $position        = null;
            $file            = null;

            foreach ($this->operationColumns as $operationColumn):

                $columnName      = $operationColumn['name'];
                $columnPath      = $this->tablePath . $columnName . '.db';
                $capacity        = $operationColumn['capacity'];
                $columnPositions = $this->positions[$columnName];

                /**
                 * Check every column entry is eligible for the operation.
                 */
                foreach ($columnPositions as $columnPosition):

                    $key      = $columnPosition['key'];
                    $position = $columnPosition['position'];

                    $file = $this->columns[$columnName];
                    $file->rewind();
                    $file->fseek($position);
                    $readEntry = rtrim($file->fread($capacity), '_');

                    /**
                     * The existing column entry must be a list.
                     */
                    if (!str_contains($readEntry, '|')):

                        throw new Exception('Entry is not a list', 400);

                    endif;

                    $readEntry = explode('|', $readEntry);

                    /**
                     * If the search is case-insensitive.
                     */
                    if (!$this->isCase):

                        $entryKey = array_search(
                            strtolower($detachItem),
                            array_map(
                                'strtolower',
                                $readEntry
                            )
                        );

                    else:

                        $entryKey = array_search($detachItem, $readEntry, true);

                    endif;

                    if (is_int($entryKey) && !$entryKey):

                        throw new Exception('Entry item could not be found', 404);

                    endif;

                endforeach;

                /**
                 * Commit the operation.
                 */
                foreach ($columnPositions as $columnPosition):

                    $key      = $columnPosition['key'];
                    $position = $columnPosition['position'];

                    $file = $this->columns[$columnName];
                    $file->rewind();
                    $file->fseek($position);
                    $readEntry = rtrim($file->fread($capacity), '_');
                    $readEntry = explode('|', $readEntry);

                    /**
                     * If the search is case-insensitive.
                     */
                    if (!$this->isCase):

                        $entryKey = array_search(
                            strtolower($detachItem),
                            array_map(
                                'strtolower',
                                $readEntry
                            )
                        );

                    else:

                        $entryKey = array_search($detachItem, $readEntry, true);

                    endif;

                    unset($readEntry[$entryKey]);

                    $compressedEntry = $this->compress($readEntry, $capacity);
                    $file->fseek($position);
                    $isWrite = $file->fwrite($compressedEntry, $capacity);

                    if (!$isWrite):

                        throw new Exception('Entry could not be sliced', 500);

                    endif;

                endforeach;

            endforeach;

            if (!$isWrite) throw new Exception('Entry could not be sliced', 500);

            return true;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Make a new table folder with a digest file.
     *
     * @throws Exception If the provided name is not safe
     * @throws Exception If the table already exists
     * @throws Exception If the table digest file could not be made
     *
     * @return bool
     */
    function makeTable(): bool {

        try {

            if (!$this->isSafe($this->tableName)):

                throw new Exception('Table name contains potentially harmful characters', 400);

            endif;

            $this->tablePath = $this->databasePath . $this->tableName . '/';

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            /**
             * Make the folder.
             */

            if (is_dir($this->tablePath)) throw new Exception('Table already exists', 400);

            mkdir($this->tablePath, 0755);

            /**
             * Make the digest file.
             */
            file_put_contents($this->digestPath, '');
            chmod($this->digestPath, 0644);

            if (!is_file($this->digestPath)) throw new Exception('Digest could not be made', 500);

            return true;

        } catch (Exception $error) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Make a new column inside an existing table.
     *
     * @param string   $columnName Name of the new column
     * @param int|null $capacity   Maximum character length of the new column
     *
     * @throws Exception If the provided name is not safe
     * @throws Exception If the digest file does not exist or is not writeable
     * @throws Exception If the column already exists
     * @throws Exception If the column could not be made
     *
     * @return bool
     */
    function makeColumn(string $columnName, int $capacity = 50): bool {

        try {

            if (!$this->isSafe($columnName)):

                throw new Exception('Table name contains potentially harmful characters', 400);

            endif;

            /**
             * Limit the capacity range.
             */
            if ($capacity < 1):

                $capacity = 1;

            elseif ($capacity > 100):

                $capacity = 100;

            endif;

            if (!file_exists($this->digestPath) || !is_writeable($this->digestPath)):

                throw new Exception('Digest does not exist or is not writeable', 400);

            endif;

            $this->digest = json_decode(file_get_contents($this->digestPath), true) ?? [];
            $columnPath   = $this->tablePath . $columnName . '.db';
            $digestNames  = array_column($this->digest, 'name') ?? [];

            if (file_exists($columnPath) || in_array($columnName, $digestNames)):

                throw new Exception('Column already exists', 400);

            endif;

            foreach ($this->columns as $column):

                $isLock = $this->lock($column);

                if (!$isLock) throw new Exception('Column could not be locked', 400);

            endforeach;

            file_put_contents($columnPath, '');

            $digestElement = [
                'name'     => $columnName,
                'capacity' => $capacity
            ];

            if ($this->digest):

                $this->digest[] = $digestElement;

                $newDigest = $this->digest;

            else:

                $newDigest[] = $digestElement;

            endif;

            /**
             * Write back to the digest file.
             */
            $jsonDigest = json_encode($newDigest, JSON_PRETTY_PRINT);
            file_put_contents($this->digestPath, $jsonDigest);
            chmod($this->digestPath, 0644);

            if (!$this->digestPath) throw new Exception('Column could not be made', 500);

            return true;

        } catch (Exception $exception) {

            if (!$this->isReport) return false;

            $this->handleException($exception);
        }
    }

    /** ---------------------------------------------------------------------------------------- */

    /**
     * Utilities.
     */

    /**
     * Pad a column entry with empty space characters.
     *
     * @param mixed @entry    Column entry to be padded
     * @param int   @capacity Total character length of the compressed entry
     *
     * @return string|false
     */
    private function compress(mixed $entry, int $capacity): string|false {

        if (is_array($entry)) $entry = implode('|', $entry);

        if (strlen($entry) > $capacity) return false;

        $padded = str_pad(substr($entry, 0, $capacity), $capacity, '_');

        return $padded;
    }

    /**
     * Try to lock an SPL file object resource. Give up after n attempts.
     *
     * @param $resource SPL file object resource
     *
     * @return bool
     */
    private function lock($resource): bool {

        $attempt = 2;
        $lock    = null;

        while ($attempt > 0):

            $lock = $resource->flock(LOCK_EX | LOCK_NB);

            if (!$lock):

                sleep(1);
                $attempt --;

                if ($attempt === 0):

                    return false;

                endif;

            else:

                $attempt = 0;

            endif;

        endwhile;

        return $lock;
    }

    /**
     * Check if an input contains any potentially harmful characters.
     *
     * @param $resource SPL file object resource
     *
     * @return bool
     */
    private function isSafe(mixed $input): bool {

        $chars = ['..', '/', '<', '>', '$'];

        if (is_array($input)):

            $input = json_encode($input);

        else:

            $input = (string) $input;

        endif;

        foreach ($chars as $char):

            if (str_contains($input, $char)) return false;

        endforeach;

        return true;
    }

    /**
     * Handle a thrown exception.
     *
     * @param $exception
     *
     * @return void
     */
    public function handleException(Exception $exception): void {

        die(json_encode([
            'success' => false,
            'code'    => $exception->getCode(),
            'message' => $exception->getMessage()
        ]));
    }
}
