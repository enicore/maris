<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Standalone\Database;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\MySql;

#[CoversClass(\Enicore\Maris\Standalone\Database::class)]
class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = MySql::connect($this);
        $this->db->query('DROP TABLE IF EXISTS maris_items');
        $this->db->query('CREATE TABLE maris_items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), ' .
            'position INT) DEFAULT CHARSET=utf8mb4');

        foreach (['c' => 3, 'a' => 1, 'b' => 2, 'd' => 4] as $name => $position) {
            $this->db->insert('maris_items', ['name' => $name, 'position' => $position]);
        }
    }

    protected function tearDown(): void
    {
        isset($this->db) && $this->db->query('DROP TABLE IF EXISTS maris_items');
    }

    private function names(array|bool $rows): array
    {
        return $rows ? array_column($rows, 'name') : [];
    }

    public function testConnectionError(): void
    {
        $this->expectException(PDOException::class);
        new Database(getenv('MARIS_TEST_DB_HOST'), getenv('MARIS_TEST_DB_PORT') ?: 3306, 'maris_missing_db',
            'maris_wrong_user', 'wrong');
    }

    public function testQuery(): void
    {
        $this->assertSame('a', $this->db->query('SELECT name FROM maris_items WHERE position = ?', 1)->fetchColumn());
        $this->assertSame('b', $this->db->query('SELECT name FROM maris_items WHERE position = ?', [2])->fetchColumn());
        $this->assertSame(4, $this->db->query('SELECT COUNT(*) FROM maris_items')->fetchColumn());

        $this->expectException(PDOException::class);
        $this->db->query('SELECT missing FROM maris_items');
    }

    public function testAllAndRow(): void
    {
        $this->assertSame(['a', 'b'], $this->names($this->db->all(
            'SELECT name FROM maris_items WHERE position < ? ORDER BY position', 3)));
        $this->assertSame([], $this->db->all('SELECT name FROM maris_items WHERE position > 10'));

        $this->assertSame(['name' => 'c', 'position' => 3],
            $this->db->row('SELECT name, position FROM maris_items WHERE name = ?', 'c'));
        $this->assertFalse($this->db->row('SELECT name FROM maris_items WHERE name = ?', 'x'));
    }

    public function testGetAll(): void
    {
        $this->assertSame(['c', 'a', 'b', 'd'], $this->names($this->db->getAll('maris_items')));
        $this->assertSame(['a', 'b', 'c', 'd'], $this->names($this->db->getAll('maris_items', 'name', [], 'position')));
        $this->assertSame(['d', 'c'],
            $this->names($this->db->getAll('maris_items', ['id', 'name'], [], 'position', 'DESC', 2)));
        // the offset skips rows
        $this->assertSame(['b', 'c'], $this->names($this->db->getAll('maris_items', '*', [], 'position', 'ASC', 2, 1)));
        $this->assertSame(['d'], $this->names($this->db->getAll('maris_items', '*', [], 'position', 'ASC', 2, 3)));
        $this->assertSame(['b'], $this->names($this->db->getAll('maris_items', '*', ['position' => 2, 'name' => 'b'])));
        $this->assertFalse($this->db->getAll('maris_items', '*', ['name' => 'x']));
    }

    public function testGetFirst(): void
    {
        $this->assertSame('c', $this->db->getFirst('maris_items')['name']);
        $this->assertSame('a', $this->db->getFirst('maris_items', 'name', [], 'position')['name']);
        $this->assertSame(['name' => 'd'], $this->db->getFirst('maris_items', ['name'], [], 'position', 'DESC'));
        $this->assertSame(['position' => 2], $this->db->getFirst('maris_items', 'position', ['name' => 'b']));
        $this->assertFalse($this->db->getFirst('maris_items', '*', ['name' => 'x']));
    }

    public function testExistsAndCount(): void
    {
        $this->assertTrue($this->db->exists('maris_items'));
        $this->assertTrue($this->db->exists('maris_items', 'name = ?', ['a']));
        $this->assertFalse($this->db->exists('maris_items', 'name = :name', [':name' => 'x']));

        $this->assertSame(4, $this->db->count('maris_items'));
        $this->assertSame(2, $this->db->count('maris_items', 'position > ?', [2]));
        $this->assertSame(0, $this->db->count('maris_items', 'name = ?', ['x']));
    }

    public function testInsert(): void
    {
        $this->assertNotFalse($this->db->insert('maris_items', ['name' => 'Emoji 😀 é', 'position' => 5]));
        $id = $this->db->getLastInsertId();

        $this->assertSame('5', $id);
        $this->assertSame('Emoji 😀 é', $this->db->getFirst('maris_items', 'name', ['id' => $id])['name']);
    }

    public function testUpdate(): void
    {
        $result = $this->db->update('maris_items', ['name' => 'x', 'position' => 9], ['name' => 'a']);
        $this->assertSame(1, $result->rowCount());
        $this->assertSame(['name' => 'x', 'position' => 9],
            $this->db->getFirst('maris_items', 'name, position', ['id' => 2]));
        $this->assertSame(3, $this->db->count('maris_items', 'position < 9'));

        // the same field in the data and the condition
        $this->db->update('maris_items', ['position' => 8], ['position' => 9]);
        $this->assertSame(8, $this->db->getFirst('maris_items', 'position', ['id' => 2])['position']);
    }

    public function testDelete(): void
    {
        $this->assertSame(1, $this->db->delete('maris_items', ['name' => 'a', 'position' => 1])->rowCount());
        $this->assertSame(0, $this->db->delete('maris_items', ['name' => 'b', 'position' => 1])->rowCount());
        $this->assertSame(['c', 'b', 'd'], $this->names($this->db->getAll('maris_items')));
    }

    public function testGetLastError(): void
    {
        $this->assertSame('', $this->db->getLastError());
    }
}
