<?php
session_start();
unset($_SESSION['random_images']);
unset($_SESSION['current_search']);
http_response_code(200);