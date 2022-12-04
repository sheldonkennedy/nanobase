# Nanobase

Nanobase is a fast, lightweight relational database class for PHP, providing a simple data management solution designed for tasks where performance, memory efficiency, portability and flexibility are the priorities.

**Please note: This is a class, not a replacement for an actual database.**


Typical use cases
---

1) Registering and signing-in users on a website.
2) Storing and reading customer information.
3) Keeping track of purchase orders.

**A typical Nanobase record might look like this:**

```json
Name: John Smith
Email: example@example.com
Address: 158 Kloof Street, Gardens, Cape Town, 8001
Role: Manager
```


How it works
---

### Plain text files

Nanobase uses plain text files to store data in key/value pairs. Each file contains one column of data.

**Example of a file with three entries:**
```
00000001|John Smith___
00000002|Jane Doe_____
00000003|Douglas Adams
```

The 8-digit key links entries across files.

### Performance

Data are stored in fixed-width format, using a pipe to separate the key from the value and an underscore to pad the value when needed. Fixed-width format makes the data positions in the file predictable which massively increases search performance (because PHP knows exactly where data will be, the file pointer can quickly move to any entry in a file).

Nanobase can search a typical table with 1,000,000 records across four columns in about three seconds.

### Memory efficiency

Data are read and written using the `SplFileObject` class from the *Standard PHP Library*. `SplFileObject` lets Nanobase iterate its files without having to first load all the file contents into server memory. This makes memory overhead trivial and allows large amounts of data to be stored without having to worry about memory capacity.

### Portability

Nanobase assets are nothing more than folders and text files, so relocating, duplicating and backing up are dead simple.

### Flexibility

You can add a new column at any time and Nanobase will integrate it seamlessly.

Columns are not typed. You can add any UTF-8 character to any column.

An entry is converted to a list (array) automatically when you append an item.

### Integrity

Before any write operation, all columns are locked using PHP `flock` to avoid any possible (even if very unlikely) collisions.


Installation
---

Download the source files manually and drop them into your project.


Usage
---

For a quick demo, use this PHP code:

```php
$db = new Nanobase('path/to/sample', 'cities');
$db->search('cape town');
$result = $db->read();

print_r($result);
```


Contact
---

Feel free to mail me on sheldonkennedy@gmail.com. I'll respond as soon as I can.
