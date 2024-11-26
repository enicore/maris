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
 * @property Session $session
 * @package Enicore\Maris
 */
class Auth
{
    public function __construct(private readonly Session $session,
                                private readonly string $sessionKey = 'auth_user_data')
    {
    }

    /**
     * Retrieves the user data from the session.
     *
     * @return mixed|null The user data stored in the session or null if not set.
     */
    public function getUserData(): mixed
    {
        return $this->session->get($this->sessionKey);
    }

    /**
     * Sets the user data in the session.
     *
     * @param array $userData
     * @param bool $regenerateSessionId
     * @return void
     */
    public function setUserData(array $userData, bool $regenerateSessionId = true): void
    {
        if (empty($userData['userId'])) {
            throw new \InvalidArgumentException("User ID is required.");
        }

        // regenerate session id to prevent session fixation attacks
        if ($regenerateSessionId) {
            $this->session->regenerate();
        }

        $this->session->set($this->sessionKey, $userData);
    }

    /**
     * Removes the user data from the session.
     *
     * @param bool $destroySession
     * @return void
     */
    public function removeUserData(bool $destroySession = true): void
    {
        if ($destroySession) {
            $this->session->destroy();
        } else {
            $this->session->remove($this->sessionKey);
        }
    }

    /**
     * Checks if the user is logged in by verifying if user data exists in the session.
     *
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLoggedIn(): bool
    {
        return !empty($this->session->get($this->sessionKey));
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


}
