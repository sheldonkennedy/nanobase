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
5. [Installation](#5-installation)
6. [Demo](#6-demo)
7. [Coming next](#7-coming-next)
8. [Roadmap](#8-roadmap)
9. [Contact](#9-contact)

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

```php
$db->throw(bool $isReport = true);

$db->makeTable();
$db->makeColumn(string $columnName, int $capacity = null);
$db->make(array $newEntries);

$db->search(
    string $term = null,
    array $columns = [],
    int $limit = 1,
    bool $isWhole = false,
    bool $isCase = false
);
$db->update(string $newEntry, array $operationColumns = null);
$db->attach(string $newItem, array $operationColumns = null);
$db->detach(string $detachItem, array $operationColumns = null);

$result = $db->read();
$result = $db->list();
```


## 5. Installation

Download `src/Nanobase.php` and drop it into your project. It's that simple.


## 6. Demo

Use this PHP code for a quick demo. The first argument ('path/to/sample') should point to the folder 'sample' in this repo.

```php
$db = new Nanobase('path/to/sample', 'cities');
$db->search('cape town');
$result = $db->read();

print_r($result);
```


## 7. Coming next

- Tests.
- Full support for method chaining.


## 8. Roadmap

- Optional encryption.
- Optional column types.
- Optional unique entries per column.
- Export tables to portable formats.
- Backup/archive tables.
- Method to clean up deleted entries to improve performance.
- Build SaaS with API.


## 9. Contact

Feel free to mail me on sheldonkennedy@gmail.com. I'll respond as soon as I can.
