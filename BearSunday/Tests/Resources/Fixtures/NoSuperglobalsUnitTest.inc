<?php

namespace Fixtures\Resource;

class AuthResource
{
    // ERROR: $_GET in Resource
    public function onGet(): static
    {
        $id = $_GET['id'];

        return $this;
    }

    // ERROR: $_POST in Resource
    public function onPost(): static
    {
        $data = $_POST['data'];

        return $this;
    }

    // ERROR: $_SERVER in Resource
    public function onHead(): static
    {
        $ua = $_SERVER['HTTP_USER_AGENT'];

        return $this;
    }

    // ERROR: $GLOBALS in Resource
    public function onDelete(): static
    {
        $id = $GLOBALS['id'];

        return $this;
    }

    // OK: regular variable
    public function onOptions(): static
    {
        $allowed = ['GET', 'POST'];

        return $this;
    }
}
