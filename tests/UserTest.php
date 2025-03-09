<?php
use PHPUnit\Framework\TestCase;
use Users\Users;
require 'vendor/autoload.php';
class UserTest extends TestCase{
    private $pdo;

    protected function setUp(): void {
        // Connect to the test database
        $this->pdo = new PDO("mysql:host=localhost;dbname=kdal", "root", "");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testUserCreation() {
        // Create a user instance
        $user = new Users();
        $user->name = "John Doe";
        $user->email = "john@example.com";
        $user->age=99;
        $user->dob="1999-09-12";
        $user->save();

        // Fetch the user from the database
        $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE email = ?");
        $stmt->execute(["john@example.com"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertNotEmpty($result, "User should exist in the database");
        $this->assertEquals("John Doe", $result['name']);
        $this->assertEquals("john@example.com", $result['email']);
    }
}