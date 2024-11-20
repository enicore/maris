<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

/**
 * Handles authentication-related operations, including checking if a user is logged in, retrieving user data from the
 * session, and managing user-specific information. The class interacts with the session to store, retrieve, and remove
 * user data.
 *
 * @property object|null $session
 * @package Enicore\Maris
 */
class Auth
{
    use Injection;
    use Singleton;

    /**
     * Checks if the user is logged in by verifying if user data exists in the session.
     *
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLoggedIn(): bool
    {
        return !empty($this->session->get("userData"));
    }

    /**
     * Retrieves the user data from the session.
     *
     * @return mixed|null The user data stored in the session or null if not set.
     */
    public function getUserData(): mixed
    {
        return $this->session->get("userData");
    }

    /**
     * Retrieves the user ID from the session's user data.
     *
     * @return int|null The user ID if available, or null if user data is not set.
     */
    public function getUserId(): ?int
    {
        return ($userData = $this->getUserData()) ? $userData['userId'] : null;
    }

    /**
     * Retrieves a specific piece of user data from the session.
     *
     * @param string $key The key for the user data to retrieve.
     * @return mixed|null The value associated with the specified key, or null if the key doesn't exist.
     */
    public function get(string $key): mixed
    {
        $userData = $this->getUserData();
        return $userData && array_key_exists($key, $userData) ? $userData[$key] : null;
    }

    /**
     * Sets the user data in the session.
     *
     * @param array $data An associative array of user data to store in the session.
     * @return void
     */
    public function setUserData(array $data): void
    {
        if (empty($data['userId'])) {
            throw new \InvalidArgumentException("User ID is required.");
        }

        $this->session->set("userData", $data);
    }

    /**
     * Removes the user data from the session.
     *
     * @return void
     */
    public function removeUserData(): void
    {
        $this->session->remove("userData");
    }
}
