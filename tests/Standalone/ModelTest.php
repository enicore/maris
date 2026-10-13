<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Standalone\Auth;
use Enicore\Maris\Classes\Code;
use Enicore\Maris\Standalone\Database;
use Enicore\Maris\Standalone\Model;
use Enicore\Maris\Classes\Session;
use PHPUnit\Framework\TestCase;
use Tests\Support\MarisItem;
use Tests\Support\MySql;
use Tests\Support\TestContainer;

#[CoversClass(\Enicore\Maris\Standalone\Model::class)]
class ModelTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = MySql::connect($this);
        $this->db->query('DROP TABLE IF EXISTS marisitems');
        $this->db->query('CREATE TABLE marisitems (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), ' .
            'position INT, active TINYINT, owner_id INT, modified_at DATETIME NULL)');
        $this->db->insert('marisitems', ['name' => 'first', 'position' => 3, 'active' => 0, 'owner_id' => 7,
            'modified_at' => '2020-01-01 00:00:00']);

        TestContainer::instance()->set(Database::class, $this->db);
        TestContainer::instance()->set(Auth::class, new Auth(new Session()));
    }

    protected function tearDown(): void
    {
        isset($this->db) && $this->db->query('DROP TABLE IF EXISTS marisitems');
        TestContainer::instance()->clear();
    }

    public function testConstruct(): void
    {
        $item = new MarisItem();
        $this->assertSame(['onInit'], $item->calls);
        $this->assertFalse($item->isLoaded());
        $this->assertSame([], $item->getData());

        $item = new MarisItem(1);
        $this->assertTrue($item->isLoaded());
        $this->assertSame('first', $item->name);
        $this->assertTrue($item->loaded);

        $this->assertFalse((new MarisItem(99))->isLoaded());
    }

    public function testTableName(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS maris_custom (id INT PRIMARY KEY, name VARCHAR(10))');
        $this->db->insert('maris_custom', ['id' => 1, 'name' => 'custom']);
        $model = new class(1) extends Model {
            protected string $table = 'maris_custom';
        };
        $this->db->query('DROP TABLE maris_custom');

        $this->assertSame('custom', $model->get('name'));
    }

    public function testLoad(): void
    {
        $item = new MarisItem();
        $this->assertSame(1, $item->load(1));
        $this->assertTrue($item->isLoaded());
        $this->assertSame(1, $item->load('1'));
        // encoded ids are decoded
        $this->assertSame(1, $item->load(Code::encodeId(1)));

        $this->assertFalse($item->load(0));
        $this->assertFalse($item->isLoaded());
        $this->assertFalse($item->load(''));
        $this->assertFalse($item->load('invalid'));
        $this->assertFalse($item->load(99));
        // a failed load keeps the data of the previous one
        $this->assertSame('first', $item->name);
    }

    public function testData(): void
    {
        $item = new MarisItem();
        $item->set('name', 'value');
        $item->set('', 'ignored');
        $this->assertSame('value', $item->get('name'));
        $this->assertSame('value', $item->name);
        $this->assertNull($item->get('missing'));
        $this->assertSame(['name' => 'value'], $item->getData());

        $this->assertSame($item, $item->setData(['position' => 2]));
        $this->assertSame(['position' => 2], $item->getData());
    }

    public function testGetPublicData(): void
    {
        // a new record gets the defaults
        $this->assertSame(['id' => '', 'name' => '', 'position' => '1', 'active' => '1', 'owner_id' => ''],
            (new MarisItem())->getPublicData());

        $this->assertSame(
            ['id' => Code::encodeId(1), 'name' => 'first', 'position' => '3', 'active' => '0',
                'owner_id' => Code::encodeId(7)],
            (new MarisItem(1))->getPublicData()
        );
    }

    public function testDecodeAndValidate(): void
    {
        $item = new MarisItem();
        $errors = [];
        $data = ['id' => 'x', 'name' => ' name ', 'position' => '5', 'active' => '1', 'owner_id' => Code::encodeId(7)];

        $this->assertTrue($item->decodeAndValidate($data, $errors));
        $this->assertSame([], $errors);
        $this->assertSame(['id' => 0, 'name' => 'name', 'position' => 5, 'active' => 1, 'owner_id' => 7], $data);

        $data = ['name' => ''];
        $this->assertFalse($item->decodeAndValidate($data, $errors));
        $this->assertSame(['name' => 'This value is required'], $errors);

        $data = ['name' => 'invalid'];
        $this->assertFalse($item->decodeAndValidate($data, $errors));
        $this->assertSame(['name' => 'Invalid name'], $errors);
    }

    public function testSetPublicData(): void
    {
        $item = new MarisItem(1);
        $errors = [];
        $data = ['id' => Code::encodeId(5), 'name' => 'new', 'position' => '2', 'active' => '', 'owner_id' => ''];

        $this->assertTrue($item->setPublicData($data, $errors));
        // the id isn't changed
        $this->assertSame(1, $item->id);
        $this->assertSame('new', $item->name);
        $this->assertSame(20, $item->position);
        $this->assertSame(0, $item->active);
        $this->assertSame(0, $item->owner_id);

        $this->assertFalse($item->setPublicData(['name' => 'invalid'], $errors));
        $this->assertSame('new', $item->name);
    }

    public function testSaveNew(): void
    {
        $item = new MarisItem();
        $item->setData(['name' => 'second', 'position' => 2]);

        $this->assertSame(2, $item->save(['option' => true]));
        $this->assertSame(2, (int)$item->id);
        $this->assertSame(
            ['onInit', ['beforeSave', ['option' => true]], ['afterSave', true, ['option' => true]]],
            $item->calls
        );
        // the data changed by beforeSave() is saved, the model keeps its own
        $this->assertSame('second', $item->name);
        $this->assertSame('SECOND', $this->db->getFirst('marisitems', 'name', ['id' => 2])['name']);

        // a record with an id that isn't in the table is inserted
        $item = new MarisItem();
        $item->setData(['id' => 10, 'name' => 'tenth']);
        $this->assertSame(10, $item->save());
        $this->assertSame('TENTH', $this->db->getFirst('marisitems', 'name', ['id' => 10])['name']);
    }

    public function testSaveExisting(): void
    {
        $item = new MarisItem(1);
        $item->set('position', 4);

        $this->assertSame(1, $item->save());
        $this->assertSame(['afterSave', false, []], end($item->calls));
        $row = $this->db->getFirst('marisitems', '*', ['id' => 1]);
        $this->assertSame('FIRST', $row['name']);
        $this->assertSame(4, $row['position']);
        // modified_at is updated when the record has it
        $this->assertGreaterThan('2020-01-01 00:00:00', $row['modified_at']);
        $this->assertSame(1, $this->db->count('marisitems'));
    }

    public function testSaveAborted(): void
    {
        $item = new MarisItem(1);
        $item->set('name', 'changed');
        $item->abort = true;

        $this->assertFalse($item->save());
        $this->assertSame('first', $this->db->getFirst('marisitems', 'name', ['id' => 1])['name']);
        $this->assertNotContains('afterSave', array_column(array_filter($item->calls, 'is_array'), 0));
    }

    public function testDelete(): void
    {
        $this->assertFalse((new MarisItem())->delete());

        $item = new MarisItem(1);
        $item->abort = true;
        $this->assertFalse($item->delete());
        $this->assertSame(1, $this->db->count('marisitems'));

        $item->abort = false;
        $this->assertTrue($item->delete());
        $this->assertSame(['onInit', 'beforeDelete', 'beforeDelete', 'afterDelete'], $item->calls);
        $this->assertFalse($item->id);
        $this->assertSame(0, $this->db->count('marisitems'));
    }
}
