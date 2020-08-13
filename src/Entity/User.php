<?php


namespace steph\db_query\Entity;


class User
{

    /**
     * @var int autoincrement
     *
     */
    protected $id;

    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    protected $password;
    /**
     * @var string
     */
    protected $email;
    /**
     * @var int
     */
    protected $rank;

    /**
     * @param int $id
     * @return User
     */
    public function setId(int $id): User
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param mixed $username
     * @return User
     */
    public function setUsername($username) : User
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $password
     * @return User
     */
    public function setPassword(string $password): User
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $email
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param int $rank
     * @return User
     */
    public function setRank(int $rank): User
    {
        $this->rank = $rank;
        return $this;
    }

    /**
     * @return int
     */
    public function getRank(): int
    {
        return $this->rank;
    }
}