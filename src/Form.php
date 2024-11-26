<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

/**
 * This class represents a form with HTML elements. It allows setting, validating, and extracting form data. The form
 * handles elements (inputs, selects, etc.) and data (key-value pairs) for submission and validation.
 *
 * There are two main concepts:
 * 1. Elements: A list of HTML form elements including their metadata (e.g., title, value, error state, etc.).
 * 2. Data: A key-value array where each key corresponds to an element, and the value is the form input's data.
 *
 * The class provides methods to set form elements, validate the form data, handle errors, and extract data.
 *
 * There are functions to set elements and data. Data can be set from any key/value source, for example from the
 * database. So initially we set up the elements and set the data with validation turned off. Then we send the elements
 * to the frontend, render the form, and get the elements back - but with the value fields updated. Then we need to
 * extract the data from that elements array (extractDataFromElements()) and set that data with validation turned on.
 * This updates the elements' values and error fields which we can then send back to the frontend and save in the db.
 *
 * @package Enicore\Maris
 */
class Form
{
    private array $elements = [];
    private array $inputs = ["text", "textarea", "select", "check", "image", "color", "custom"];
    private ?Database $db;

    /**
     * Constructor method to initialize form elements.
     *
     * @param array $elements Initial elements to be set in the form.
     */
    public function __construct(array $elements = [])
    {
        $this->db = Di::db();
        $this->setElements($elements);
    }

    /**
     * Returns the array of elements for the form.
     *
     * @return array Array of form elements.
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * Sets the form elements, adding missing properties to each element. This method checks each element and ensures
     * that required properties like `title`, `info`, `value`, etc., are set. If a property is missing, a default value
     * is provided.
     *
     * @param array $elements The form elements to be set.
     */
    public function setElements(array $elements): void
    {
        $this->elements = [];

        foreach ($elements as $key => $element) {
            // make sure the input is supported
            if (!isset($element['input']) || !in_array($element['input'], $this->inputs)) {
                continue;
            }

            // add the missing properties
            isset($element['title']) || ($element['title'] = "");
            isset($element['info']) || ($element['info'] = "");
            isset($element['tab']) || ($element['tab'] = "");
            isset($element['value']) || ($element['value'] = "");
            isset($element['htmlBefore']) || ($element['htmlBefore'] = "");
            isset($element['htmlAfter']) || ($element['htmlAfter'] = "");
            $element['input'] == "select" && !isset($element['options']) && ($element['options'] = []);
            $element['input'] == "text" && !isset($element['placeholder']) && ($element['placeholder'] = "");

            $this->elements[$key] = $element;
        }
    }

    /**
     * Sets a specific property of a form element.
     *
     * @param string $key The key of the element to modify.
     * @param string $property The property of the element to set.
     * @param mixed $value The value to set for the property.
     */
    public function setElementProperty(string $key, string $property, mixed $value): void
    {
        if (isset($this->elements[$key])) {
            $this->elements[$key][$property] = $value;
        }
    }

    /**
     * Sets form data from a key-value array and validates the data. This method iterates over the form elements and
     * assigns the corresponding values from the provided data array. It also validates the data based on rules set for
     * each element (e.g., required, length, unique).
     *
     * @param array $data The data to set for the elements.
     * @param bool $validate Whether to validate the data (defaults to true).
     * @return bool Returns true if validation passed, false if there were errors.
     */
    public function setData(array $data, bool $validate = true): bool
    {
        $result = true;

        // reset all the error properties
        foreach ($this->elements as $key => $element) {
            $this->elements[$key]['error'] = false;
        }

        foreach ($this->elements as $key => $element) {
            // make sure the value exists in data
            isset($data[$key]) || ($data[$key] = "");

            // validate the data according to the rules set in the element
            if ($validate) {
                if (!empty($element['required']) && empty($data[$key])) {
                    $this->elements[$key]['error'] = "This value is required.";
                    $result = false;
                }

                if (!empty($element['required_unique'])) {
                    if ($this->db->row(
                        "SELECT id FROM `{$element['required_unique']}` WHERE $key = ? AND (id != ? OR ? IS NULL) LIMIT 1",
                        [$data[$key], $data['id'], $data['id']])
                    ) {
                        $this->elements[$key]['error'] = "A record with this value already exists.";
                        $result = false;
                    }
                }

                if (!empty($element['required_length']) && strlen($data[$key]) != $element['required_length']) {
                    $this->elements[$key]['error'] = "This value must be {$element['required_length']} characters long.";
                    $result = false;
                }

                if ($element['input'] == "select" &&
                    isset($element['options']) &&
                    !array_key_exists($data[$key], $element['options'])
                ) {
                    // validate selects: check if the incoming value is in the keys of the select options
                    $this->elements[$key]['error'] = "This selection is invalid";
                    $result = false;
                }
            }

            // convert the data to the correct format (all the values from the frontend come in as strings)
            $this->elements[$key]['value'] = match ($element["input"]) {
                "check" => (int)($data[$key] == "1"),
                default => (string)$data[$key],
            };

            if ($element["input"] == "image" && !empty($element['value'])) {
                $this->elements[$key]['uploaded'] = true;
            }
        }

        return $result;
    }

    /**
     * Sets form data from the request, using the provided record ID.
     *
     * @param int|string $recordId The ID of the record to fetch data for.
     * @return bool Returns true if data was set successfully, false otherwise.
     */
    public function setDataFromRequest(int|string $recordId): bool
    {
        return $this->setDataFromElements($recordId, $this->request->get("elements"));
    }

    /**
     * Sets form data using an array of elements, including the record ID.
     *
     * @param int|string $recordId The ID of the record.
     * @param array $elements The elements containing the data.
     * @return bool Returns true if data was set successfully, false otherwise.
     */
    public function setDataFromElements(int|string $recordId, array $elements): bool
    {
        // if the id is not a number, try decoding it
        if (is_string($recordId) && !is_numeric($recordId)) {
            $recordId = Code::decodeId($recordId);
        }

        return $this->setData(['id' => $recordId] + self::extractDataFromElements($elements));
    }

    /**
     * Returns the extracted data from the form elements as a key/value array.
     *
     * @return array The extracted data in key/value format.
     */
    public function getData(): array
    {
        return $this->extractDataFromElements($this->elements);
    }

    /**
     * Sets errors for specific form elements based on the provided error array.
     *
     * @param array $errors The array of errors with element keys as the keys.
     */
    public function setErrors(array $errors): void
    {
        foreach ($errors as $key => $value) {
            if (array_key_exists($key, $this->elements) && !empty($value)) {
                $this->elements[$key]['error'] = $value;
            }
        }
    }

    /**
     * Extracts the data from the form elements and returns it as a key/value array.
     *
     * @param array $elements The elements from which to extract data.
     * @return array The extracted data in key/value format.
     */
    public static function extractDataFromElements(array $elements): array
    {
        return array_map(function ($element) {
            return $element['value'] ?? "";
        }, $elements);
    }
}
