<?php
namespace Tests\Support;

use Enicore\Maris\Standalone\Model;
use Exception;

/**
 * A model for ModelTest, saved in the table "marisitems" (named after the class), that records its hook calls.
 */
class MarisItem extends Model
{
    public array $calls = [];
    public bool $abort = false;

    protected function onInit()
    {
        $this->calls[] = 'onInit';
        $this->publicFields['name'] = ['type' => 'string', 'required' => true];
        $this->publicFields['position'] = ['type' => 'integer', 'default' => 1];
        $this->publicFields['active'] = ['type' => 'boolean', 'default' => true];
        $this->publicFields['owner_id'] = ['type' => 'int', 'encoded' => true];
    }

    protected function onValidate(array &$data, array &$errors): bool
    {
        if ($data['name'] === 'invalid') {
            $errors['name'] = 'Invalid name';
            return false;
        }

        $data['name'] = trim($data['name']);
        return true;
    }

    protected function afterLoad(array $data): array
    {
        $data['loaded'] = true;
        return $data;
    }

    protected function beforeSetPublicData(&$publicData): void
    {
        $publicData['position'] *= 10;
    }

    protected function beforeSave(array $data, array $parameters): array
    {
        $this->calls[] = ['beforeSave', $parameters];
        $this->abort && throw new Exception('aborted');
        unset($data['loaded']);
        $data['name'] = strtoupper($data['name']);
        return $data;
    }

    protected function afterSave(bool $newRecord, array $parameters)
    {
        $this->calls[] = ['afterSave', $newRecord, $parameters];
    }

    protected function beforeDelete(): void
    {
        $this->calls[] = 'beforeDelete';
        $this->abort && throw new Exception('aborted');
    }

    protected function afterDelete(): void
    {
        $this->calls[] = 'afterDelete';
    }
}
