# Nanobase

Nanobase is a fast, lightweight relational database management class for PHP, providing a simple data management solution for tasks where performance, memory efficiency, portability and flexibility are the priorities.

**Please note:** This is a database management *class* that focuses on datapoints, not a replacement for a full database management system!


## Contents

1. [Typical use cases](#1-typical-use-cases)
2. [How it works](#2-how-it-works)
    - [Performance](#performance)
    - [Memory efficiency](#memory-efficiency)
    - [Portability](#portability)
    - [Flexibility](#flexibility)
    - [Integrity](#integrity)
3. [Quick example](#3-quick-example)
4. [Public methods](#4-public-methods)
5. [Gotchas](#5-gotchas)
6. [Installation](#6-installation)
7. [Demo](#7-demo)
8. [Coming next](#8-coming-next)
9. [Roadmap](#9-roadmap)
10. [Contact](#10-contact)

## 1. Typical use cases

- Registering and authenticating users on a website.
- Storing and reading customer information.
- Keeping track of purchase orders.


## 2. How it works

Nanobase uses plain text files (with a .db extension) to store data in key/value pairs. Each file contains one column of data in fixed-width format.

Example of a file with three entries:
```
00000001|John________________
00000002|Jane________________
00000003|Andrew-Saint-Germain
```

The 8-digit key on the left of each row links entries across files to form a unique record.

### Performance

Data are stored in fixed-width format, using a pipe to separate the key from the value and an underscore to pad the value when needed. Fixed-width format makes the data positions in the file predictable which massively increases search performance (because we know exactly where all entries begin, we can quickly move the file pointer to any entry in the file).

Nanobase can search a typical table with 1,000,000 records across four columns in about three seconds.

### Memory efficiency

Data are read and written using the `SplFileObject` class from the *Standard PHP Library*. `SplFileObject` lets Nanobase iterate its files without having to first load all the file contents into server memory. This makes memory overhead trivial and allows large amounts of data to be accessed without having to worry about memory overload.

### Portability

Nanobase assets are nothing more than folders and text files, so relocating, duplicating and backing up are dead simple.

### Flexibility

You can add a new column at any time and Nanobase will integrate it seamlessly.

Columns are not typed by default. You can add any UTF-8 character to any column.

An entry is converted to a list (array) automatically when you append an item.

### Integrity

Before any write operation, all columns are locked using PHP `flock` to avoid any possible (even if very unlikely) collisions.

Reserved and potentially unsafe characters are prevented from writing.


## 3. Quick example

```php
// instantiate the Nanobase class and make a new table called "users" with four columns
// argument 2 in "makeColumn" sets the maximum character length for column entries

$db = new Nanobase('path/to/database', 'users');
$db->makeTable();
$db->makeColumn('userId', 8);
$db->makeColumn('firstName', 20);
$db->makeColumn('surname', 20);
$db->makeColumn('email', 50);
```

```php
// add John as a user

$db->make([
    'userId'    => '10000001',
    'email'     => 'example@example.com',
    'firstName' => 'John',
    'surname'   => 'Smith'
]);
```

```php
// search for John in the "firstName" column and display the first record found

$db->search('john', ['firstName']);
$result = $db->read();
print_r($result);
```

Result

```php
Array
(
    [userId] => 10000001
    [firstName] => John
    [surname] => Smith
    [email] => example@example.com
)
```


## 4. Public methods

More detailed comments are available in the Nanobase class.

```php
// return a short JSON report, or bool on exception

$db->throw(bool $isReport = true);

```

```php
// make a new table using constructor argument 2 as the name

$db->makeTable();
```

```php
// make a new column in the table with max. character length (hard limit is 100)

$db->makeColumn(string $columnName, int $capacity = null);
```

```php
// make a new record with each column name and value as a key/value pair

$db->make(array $newEntries);
```

```php

$db->count(
    string $term    = null,  // phrase to search for (search for all by default)
    array  $columns = [],    // columns to search through (search through all by default)
    bool   $isWhole = false, // match search phrase to entire column entry, or partial match
    bool   $isCase  = false  // case-sensitive, or case-insensitive phrase match
);
```

```php

$db->search(
    string $term    = null,  // phrase to search for (search for all by default)
    array  $columns = [],    // columns to search through (search through all by default)
    int    $limit   = 1,     // max. number of records to find (hard limit is 100)
    bool   $isWhole = false, // match search phrase to entire column entry, or partial match
    bool   $isCase  = false  // case-sensitive, or case-insensitive phrase match
);
```

```php
// overwrite the found column entries, else create a new entry

$db->update(string $newEntry, array $operationColumns = null);
```

```php
// convert the found column entries to a list and append a new item

$db->attach(string $newItem, array $operationColumns = null);
```

```php
// remove a list item from the found column entries

$db->detach(string $detachItem, array $operationColumns = null);
```

```php
// display the first record found

$result = $db->read();
```

```php
// display all records found

$result = $db->list();
```

## 5. Gotchas

- The column .db files use the Windows standard `\r\n` ([carriage return + line feed](https://stackoverflow.com/questions/15433188/what-is-the-difference-between-r-n-r-and-n)) invisible characters at the end of each row. If you edit a column file manually, then ensure you follow the same format or you will get misaligned records.


## 6. Installation

Download `src/Nanobase.php` and drop it into your project. It's that simple.


## 7. Demo

Use this PHP code for a quick demo. The first argument ('path/to/sample') should point to the folder 'sample' in this repo.

```php
$db = new Nanobase('path/to/sample', 'cities');
$db->search('cape town');
$result = $db->read();

print_r($result);
```


## 8. Coming next

- Tests.
- Full support for method chaining.


## 9. Roadmap

- Optional encryption.
- Optional column types.
- Optional unique entries per column.
- Export tables to portable formats.
- Backup/archive tables.
- Method to clean up deleted entries to improve performance.
- Build SaaS with API.


## 10. Contact

Feel free to mail me on sheldonkennedy@gmail.com. I'll respond as soon as I can.
