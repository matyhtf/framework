<?php

namespace SPF\Exception;

class ValidateException extends LogicException
{
    protected $errors = [];

    public function __construct($errors, $msg = 'The given data was invalid.')
    {
        $this->errors = $errors;

        parent::__construct($msg, 400);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
