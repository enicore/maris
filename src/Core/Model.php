<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Core;

/**
 * A base model class providing essential CRUD operations and data handling for derived models in the framework.
 *
 * This class provides methods to load, save, delete, and manipulate model data, with hooks that derived classes can
 * override for custom initialization, validation, and data processing. It also includes mechanisms for public field
 * handling, including encoding and decoding for secure data transmission.
 *
 * This base model expects subclasses to define their specific table structure, public fields, and optional validations.
 *
 * @package Enicore\Maris
 */
class Model
{
    protected string $table = ""; // can be overloaded in the derived classes
    protected array $publicFields = [];
    protected array $data = [];
    private bool $loaded = false;
    protected ?Auth $auth;
    protected ?Database $db;

    /**
     * Model constructor. Initializes the table name, sets dependencies, runs initialization hooks,
     * and optionally loads data if an ID is provided.
     *
     * @param int|string $id Optional ID for loading the initial record data.
     */
    public function __construct($id = 0)
    {
        $this->auth = Di::auth();
        $this->db = Di::db();

        // set the table name to plural of the model name
        if (empty($this->table)) {
            $array = explode("\\", get_class($this));
            $this->table = strtolower(array_pop($array)) . "s";
        }

        // add id to the public fields, we should always send it to the frontend and always have it encoded
        $this->publicFields["id"] = ["type" => "integer", "encoded" => true, "default" => ""];

        // run onInit in the derived classes
        $this->onInit();

        // if the id is specified, load the data
        if (!empty($id)) {
            $this->load($id);
        }
    }

    /**
     * Magic getter to access data fields.
     *
     * @param string $name Name of the field.
     * @return mixed The field value or null if not set.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Retrieves a field value from the data array.
     *
     * @param string $name Name of the field.
     * @return mixed The field value or null if not set.
     */
    public function get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Returns the complete data array representing the record fields.
     *
     * @return array The data array.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets a field value in the data array.
     *
     * @param string $key Field name.
     * @param mixed $value Value to set.
     */
    public function set(string $key, mixed $value): void
    {
        if (!empty($key)) {
            $this->data[$key] = $value;
        }
    }

    /**
     * Sets the object's data that will be saved to the database.
     *
     * @param array $data The new data array.
     * @return $this Fluent interface.
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Returns the data as specified in publicFields for use in the frontend. It encodes the required ids and converts
     * the data to the correct types.
     *
     * @return array
     */
    public function getPublicData(): array
    {
        $data = [];
        $fields = $this->publicFields;


        foreach ($fields as $key => $options) {
            $data[$key] = !empty($this->data['id']) ? $this->data[$key] ?? "" : $options['default'] ?? "";
            switch ($options["type"]) {
                case "int":
                case "integer":
                    if (empty($options['encoded'])) {
                        $data[$key] = (string)(int)$data[$key];
                    } else {
                        // if the value is empty or zero, don't encode it
                        $data[$key] = (int)$data[$key] ? Code::encodeId((int)$data[$key]) : "";
                    }
                    break;
                case "bool":
                case "boolean":
                    $data[$key] = $data[$key] ? "1" : "0";
                    break;
                default:
                    $data[$key] = (string)$data[$key];
            }
        }

        return $data;
    }

    /**
     * Validates the data coming from the frontend, decodes it and sets it into internal record data that can be saved
     * to the db. Note that id is always included in the public fields so we can send it encoded to the frontend, but
     * we're not overwriting it here.
     *
     * @param array $data Input data from the frontend.
     * @param array $errors Reference to an array for error messages.
     * @return bool True on success, false on validation failure.
     */
    public function setPublicData(array $data, array &$errors = []): bool
    {
        if (!$this->decodeAndValidate($data, $errors)) {
            return false;
        }

        $this->beforeSetPublicData($data);

        foreach ($this->publicFields as $key => $options) {
            if ($key != "id") {
                $this->data[$key] = $data[$key];
            }
        }

        return true;
    }

    /**
     * Decodes and validates the data coming from the frontend. The decoding is done directly on the passed data.
     * Returns the validation result and the list of errors.
     *
     * @param array $data Data array to decode and validate.
     * @param array $errors Reference for validation error messages.
     * @return bool True if valid, false if there are errors.
     */
    public function decodeAndValidate(array &$data, array &$errors = []): bool
    {
        $errors = [];

        foreach ($this->publicFields as $key => $options) {
            $data[$key] = $data[$key] ?? "";

            if (!empty($options['required']) && empty($data[$key])) {
                $errors[$key] = "This value is required";
                return false;
            }

            $data[$key] = match ($options["type"]) {
                "int", "integer" => empty($options['encoded']) ? (int)$data[$key] : (int)Code::decodeId($data[$key]),
                "bool", "boolean" => $data[$key] ? 1 : 0,
                default => (string)$data[$key],
            };
        }

        return $this->onValidate($data, $errors);
    }

    /**
     * Loads a record from the database by ID.
     *
     * @param int|string $id ID to load.
     * @return int|bool The ID on success or false on failure.
     */
    public function load(int|string $id): int|bool
    {
        $this->loaded = false;

        if (empty($id)) {
            return false;
        }

        // if the id is not a number, try decoding it
        if (is_string($id) && !is_numeric($id)) {
            $id = Code::decodeId($id);
        }

        if (!empty($id) && $data = $this->db->getFirst($this->table, "*", ["id" => $id])) {
            $this->data = $this->afterLoad($data);
            $this->loaded = true;
            return $id;
        }

        return false;
    }

    /**
     * Returns true if the last load() operation was successful; false otherwise. This is useful if we pass the id to
     * the constructor to load the data -- in this case we don't know if the load was successful, so we can check it
     * using this function.
     *
     * @return bool True if loaded, false otherwise.
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Saves the record to the database. Parameters will be passed to beforeSave and afterSave that can be overwritten
     * in the derived classes.
     *
     * @param array $parameters Optional parameters for beforeSave and afterSave hooks.
     * @return int|bool The record ID on success, false on failure.
     */
    public function save(array $parameters = []): int|bool
    {
        // before save callback - throw an exception to abort
        try {
            $data = $this->beforeSave($this->data, $parameters);
        } catch (\Exception) {
            return false;
        }

        if (empty($data['id']) || !$this->db->getFirst($this->table, "id", ["id" => $data['id']])) {
            if (!$this->db->insert($this->table, $data)) {
                return false;
            }
            $this->data['id'] = $this->db->getLastInsertId();
            $newRecord = true;
        } else {
            isset($data['modified_at']) && ($data['modified_at'] = date("Y-m-d H:i:s"));
            if (!$this->db->update($this->table, $data, ["id" => $data['id']])) {
                return false;
            }
            $newRecord = false;
        }

        $this->afterSave($newRecord, $parameters);

        return $this->data['id'];
    }

    /**
     * Deletes the record from the database.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(): bool
    {
        if (empty($this->data['id'])) {
            return false;
        }

        // before delete callback - throw an exception to abort
        try {
            $this->beforeDelete();
        } catch (\Exception) {
            return false;
        }

        if (!$this->db->delete($this->table, ["id" => $this->data['id']])) {
            return false;
        }

        $this->afterDelete();

        $this->data['id'] = false;
        return true;
    }

    /**
     * Gets called after the object is created. Can be overloaded in the derived classes.
     */
    protected function onInit()
    {
    }

    /**
     * This function is called to validate the data that is about to be saved. Can be overloaded in the derived classes.
     *
     * @param array $data The data to be saved, it can be modified during validation if need be.
     * @param array $errors Should return the list of validation errors, with keys representing the fields and values
     *                      representing the error messages.
     * @return bool Should return true if validation is successful, false otherwise.
     */
    protected function onValidate(array &$data, array &$errors): bool
    {
        return true;
    }

    /**
     * Called after the data is loaded from the db. Takes the loaded data as a parameter and should return the data that
     * will be loaded into the model's data array. Can be overloaded in the derived classes.
     *
     * @param array $data
     * @return array
     */
    protected function afterLoad(array $data): array
    {
        return $data;
    }

    /**
     * Called before the data from post is applied to the model. The new data can be changed here before it's applied.
     * Can be overloaded in the derived classes.
     *
     * @param $publicData
     * @return void
     */
    protected function beforeSetPublicData(&$publicData): void
    {
    }

    /**
     * Called before the data is saved to the database. Takes the data that is about to be saved as a parameter and
     * returns the data that will be saved. The returned data does not overwrite the current model's data. Can be
     * overloaded in the derived classes.
     *
     * @param array $data Data to be saved.
     * @param array $parameters This array is passed on to this function from save()
     * @return array
     */
    protected function beforeSave(array $data, array $parameters): array
    {
        return $data;
    }

    /**
     * Called after saving a record. Can be overloaded in the derived classes.
     *
     * @param bool $newRecord True if the record was inserted; false if it was updated
     * @param array $parameters This array is passed on to this function from save()
     */
    protected function afterSave(bool $newRecord, array $parameters)
    {
    }

    /**
     * Called before deleting a record. Can be overloaded in the derived classes.
     * @return void
     */
    protected function beforeDelete(): void
    {
    }

    /**
     * Called after deleting a record. Can be overloaded in the derived classes.
     * @return void
     */
    protected function afterDelete(): void
    {
    }
}
