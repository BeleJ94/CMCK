<?php

abstract class Controller
{
    protected function view($view, array $data = [], $layout = null)
    {
        $viewFile = view_path($view);

        if (!file_exists($viewFile)) {
            http_response_code(500);
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        if ($layout !== null) {
            ob_start();
            require $viewFile;
            $content = ob_get_clean();
            $layoutFile = view_path($layout);

            if (!file_exists($layoutFile)) {
                http_response_code(500);
                throw new RuntimeException("Layout not found: {$layout}");
            }

            require $layoutFile;
            return;
        }

        require $viewFile;
    }

    protected function model($model)
    {
        $modelClass = ucfirst($model);
        $modelFile = dirname(__DIR__) . '/models/' . $modelClass . '.php';

        if (!file_exists($modelFile)) {
            throw new RuntimeException("Model not found: {$modelClass}");
        }

        require_once $modelFile;

        return new $modelClass();
    }

    protected function renderViewToString($view, array $data)
    {
        $viewFile = view_path($view);

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        return ob_get_clean();
    }

    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }
}
