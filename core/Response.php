<?php

class Response
{
    public static function json($payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    public static function ok($data = array(), $message = 'OK')
    {
        self::json(array(
            'success' => true,
            'message' => $message,
            'data' => $data,
        ));
    }

    public static function error($message, $status = 400, $data = array())
    {
        self::json(array(
            'success' => false,
            'message' => $message,
            'data' => $data,
        ), $status);
    }
}
