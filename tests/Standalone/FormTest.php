<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Code;
use Enicore\Maris\Standalone\Database;
use Enicore\Maris\Standalone\Form;
use PHPUnit\Framework\TestCase;
use Tests\Support\MySql;
use Tests\Support\TestContainer;

#[CoversClass(\Enicore\Maris\Standalone\Form::class)]
class FormTest extends TestCase
{
    private ?Database $db = null;

    protected function tearDown(): void
    {
        $this->db?->query('DROP TABLE IF EXISTS maris_users');
        TestContainer::instance()->clear();
    }

    private function form(array $elements, bool $database = false): Form
    {
        if ($database) {
            $this->db = MySql::connect($this);
            $this->db->query('DROP TABLE IF EXISTS maris_users');
            $this->db->query('CREATE TABLE maris_users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(50))');
            $this->db->insert('maris_users', ['email' => 'a@example.com']);
        }

        // the form gets the database from the container even when it doesn't use it
        TestContainer::instance()->set(Database::class, $this->db);
        return new Form($elements);
    }

    public function testSetElements(): void
    {
        $form = $this->form([
            'name' => ['input' => 'text', 'title' => 'Name'],
            'country' => ['input' => 'select'],
            'unknown' => ['input' => 'unknown'],
            'missing' => [],
        ]);
        $defaults = ['title' => '', 'info' => '', 'tab' => '', 'value' => '', 'htmlBefore' => '', 'htmlAfter' => ''];

        $this->assertSame(
            [
                'name' => ['input' => 'text', 'title' => 'Name'] + $defaults + ['placeholder' => ''],
                'country' => ['input' => 'select'] + $defaults + ['options' => []],
            ],
            $form->getElements()
        );
    }

    public function testSetElementProperty(): void
    {
        $form = $this->form(['name' => ['input' => 'text']]);
        $form->setElementProperty('name', 'title', 'Name');
        $form->setElementProperty('missing', 'title', 'Missing');

        $this->assertSame('Name', $form->getElements()['name']['title']);
        $this->assertArrayNotHasKey('missing', $form->getElements());
    }

    public function testSetData(): void
    {
        $form = $this->form([
            'name' => ['input' => 'text'],
            'agree' => ['input' => 'check'],
            'size' => ['input' => 'select', 'options' => ['s' => 'Small', 'm' => 'Medium']],
            'photo' => ['input' => 'image'],
            'empty' => ['input' => 'textarea'],
        ]);

        $this->assertTrue($form->setData(['name' => 5, 'agree' => '1', 'size' => 'm', 'photo' => 'a.png']));
        $elements = $form->getElements();
        $this->assertSame('5', $elements['name']['value']);
        $this->assertSame(1, $elements['agree']['value']);
        $this->assertFalse($elements['name']['error']);
        $this->assertTrue($elements['photo']['uploaded']);
        $this->assertSame(['name' => '5', 'agree' => 1, 'size' => 'm', 'photo' => 'a.png', 'empty' => ''],
            $form->getData());

        $this->assertTrue($form->setData(['agree' => 'yes', 'size' => 's']));
        $this->assertSame(0, $form->getElements()['agree']['value']);
    }

    public function testSetDataValidation(): void
    {
        $form = $this->form([
            'name' => ['input' => 'text', 'required' => true],
            'code' => ['input' => 'text', 'required_length' => 3],
            'size' => ['input' => 'select', 'options' => ['s' => 'Small']],
        ]);

        $this->assertFalse($form->setData(['name' => '', 'code' => 'ab', 'size' => 'x']));
        $this->assertSame(
            ['This value is required.', 'This value must be 3 characters long.', 'This selection is invalid'],
            array_column($form->getElements(), 'error')
        );
        // invalid values are kept, so the user can correct them
        $this->assertSame(['name' => '', 'code' => 'ab', 'size' => 'x'], $form->getData());

        // the errors are reset
        $this->assertTrue($form->setData(['name' => 'a', 'code' => 'abc', 'size' => 's']));
        $this->assertSame([false, false, false], array_column($form->getElements(), 'error'));

        // without validation
        $this->assertTrue($form->setData(['name' => ''], false));
        $this->assertSame([false, false, false], array_column($form->getElements(), 'error'));
    }

    public function testSetDataUnique(): void
    {
        $form = $this->form(['email' => ['input' => 'text', 'required_unique' => 'maris_users']], true);

        $this->assertFalse($form->setData(['email' => 'a@example.com']));
        $this->assertSame('A record with this value already exists.', $form->getElements()['email']['error']);
        $this->assertFalse($form->setData(['id' => 2, 'email' => 'a@example.com']));

        // the record itself has the value
        $this->assertTrue($form->setData(['id' => 1, 'email' => 'a@example.com']));
        $this->assertTrue($form->setData(['email' => 'b@example.com']));
    }

    public function testSetDataFromElements(): void
    {
        $form = $this->form(['email' => ['input' => 'text', 'required_unique' => 'maris_users']], true);
        $elements = $form->getElements();
        $elements['email']['value'] = 'a@example.com';

        $this->assertSame(['email' => 'a@example.com'], Form::extractDataFromElements($elements));
        $this->assertSame(['a' => ''], Form::extractDataFromElements(['a' => []]));

        $this->assertFalse($form->setDataFromElements(2, $elements));
        // encoded ids are decoded
        $this->assertTrue($form->setDataFromElements(Code::encodeId(1), $elements));
        $this->assertTrue($form->setDataFromElements('1', $elements));
        $this->assertSame('a@example.com', $form->getData()['email']);
    }

    public function testSetErrors(): void
    {
        $form = $this->form(['name' => ['input' => 'text'], 'email' => ['input' => 'text']]);
        $form->setData([]);
        $form->setErrors(['name' => 'Wrong name', 'email' => '', 'missing' => 'Error']);

        $this->assertSame(['Wrong name', false], array_column($form->getElements(), 'error'));
        $this->assertArrayNotHasKey('missing', $form->getElements());
    }
}
