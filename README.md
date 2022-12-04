# Nanobase

Nanobase is a fast, lightweight relational database management class for PHP, providing a simple data management solution for tasks where performance, memory efficiency, portability and flexibility are the priorities.

**Please note:** This is a database management *class*, not a replacement for an actual database!


Typical use cases
---

1) Registering and authenticating users on a website.
2) Storing and reading customer information.
3) Keeping track of purchase orders.


How it works
---

Nanobase uses plain text files (with a .db extension) to store data in key/value pairs. Each file contains one column of data in fixed-width format.

Example of a file with three entries:
```
00000001|John________________
00000002|Jane________________
00000003|Andrew-Saint-Germain
```

The 8-digit key on the left of each row links entries across files to form a unique record.

### 1. Performance

Data are stored in fixed-width format, using a pipe to separate the key from the value and an underscore to pad the value when needed. Fixed-width format makes the data positions in the file predictable which massively increases search performance (because we know exactly where all entries begin, we can quickly move the file pointer to any entry in the file).

Nanobase can search a typical table with 1,000,000 records across four columns in about three seconds.

### 2. Memory efficiency

Data are read and written using the `SplFileObject` class from the *Standard PHP Library*. `SplFileObject` lets Nanobase iterate its files without having to first load all the file contents into server memory. This makes memory overhead trivial and allows large amounts of data to be stored without having to worry about memory overload.

### 3. Portability

Nanobase assets are nothing more than folders and text files, so relocating, duplicating and backing up are dead simple.

### 4. Flexibility

You can add a new column at any time and Nanobase will integrate it seamlessly.

Columns are not typed by default. You can add any UTF-8 character to any column.

An entry is converted to a list (array) automatically when you append an item.

### 5. Integrity

Before any write operation, all columns are locked using PHP `flock` to avoid any possible (even if very unlikely) collisions.

Reserved and potentially unsafe characters are prevented from writing.


Example
---

Manually create a new, empty folder called *database*.

Instantiate the Nanobase class and make a new table called *users* with four columns:

```php
$db = new Nanobase('path/to/database', 'users');
$db->makeTable();
$db->makeColumn('userId', 8);
$db->makeColumn('firstName', 20);
$db->makeColumn('surname', 20);
$db->makeColumn('email', 50);
```

Add *John* as a user:

```php
$db->make([
    'userId' => '10000001',
    'email' => 'example@example.com',
    'firstName' => 'John',
    'surname' => 'Smith'
]);
```

Search for *John* in the *firstName* column and display his record:

```php
$db->search('john', ['firstName']);
$result = $db->read();

print_r($result);
```

Result:

```php
Array
(
    [userId] => 10000001
    [firstName] => John
    [surname] => Smith
    [email] => example@example.com
)
```


All operations
---

```php
$db = new Nanobase(string $databaseFolder, string $tableName);
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
$db->append(string $newItem, array $operationColumns = null);
$db->detach(string $detachItem, array $operationColumns = null);

$result = $db->read();
$result = $db->list();
```


Installation
---

Download `src/Nanobase.php` and drop it into your project. It's that simple.


Demo
---

Use this PHP code for a quick demo. The first argument ('path/to/sample') should point to the folder 'sample' in this repo.

```php
$db = new Nanobase('path/to/sample', 'cities');
$db->search('cape town');
$result = $db->read();

print_r($result);
```


Coming next
---

- Tests.
- Full support for method chaining.


Roadmap
---

- Optional encryption.
- Optional column types.
- Optional unique entries per column.
- Export tables to portable formats.
- Backup/archive tables.
- Clean up deleted entries to improve performance.
- Build SaaS with API.


Contact
---

Feel free to mail me on sheldonkennedy@gmail.com. I'll respond as soon as I can.
