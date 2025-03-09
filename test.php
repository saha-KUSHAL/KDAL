<?php
use Users\Users;
require 'vendor/autoload.php';

//
$user = new Users();
$user->name = "Jhon Doe";
$user->email = "Jhon@doe.com";
$user->age=21;
$user->dob='2022-19-10';
$user->save();