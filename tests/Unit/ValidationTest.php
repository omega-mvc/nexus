<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = array();
    }

    public function testValidEmailReturnsNull(): void
    {
        $_POST = array('email' => 'a@b.co');
        $validation = new Validation();
        $validation->addEmailFields('email');
        $this->assertNull($validation->validate());
    }

    public function testInvalidEmailReturnsMessagesWhenRequested(): void
    {
        $_POST = array('email' => 'not-an-email');
        $validation = new Validation();
        $validation->addEmailFields('email');
        $messages = $validation->validate(TRUE);
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('email', $messages);
    }

    public function testInvalidEmailThrowsValidationExceptionByDefault(): void
    {
        $_POST = array('email' => 'not-an-email');
        $validation = new Validation();
        $validation->addEmailFields('email');
        $this->expectException(ValidationException::class);
        $validation->validate();
    }

    public function testMissingRequiredFieldProducesMessage(): void
    {
        $_POST = array();
        $validation = new Validation();
        $validation->addRequiredFields('name');
        $messages = $validation->validate(TRUE);
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('name', $messages);
    }
}
