<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Minify the given file path.
 *
 * @param string $file The file path to minify.
 *
 * @throws Exception
 *
 * @return string The minified url.
 */
function minify(string $file): string
{
    if (config('minify.assets_enabled') === false)
        return asset($file);

    if (!minifyFileExists($file))
        throw new Exception('Cannot create minified route. File ' . $file . ' not found');

    return route('minify.assets', ['file' => $file]);
}

function minifyFilePath(string $file): string
{
    if (config('minify.assets_enabled') === false)
        throw new NotFoundHttpException();

    $storage = config('minify.assets_storage', 'resources');

    // remove slash or backslash from the beginning of the file path
    $file = ltrim($file, '/\\');

    // make sure the storage has trailing slash
    return base_path(rtrim($storage, '/') . '/' . $file);
}

function minifyFileExists(string $file): bool
{
    try
    {
        minifyFileGetContents($file);
        return true;
    } catch (NotFoundHttpException) {
        return false;
    }
}

function minifyFileGetContents(string $file): string
{
    $accessedFile = $realFile = minifyFilePath($file);

    try {
        if ($isVapor = str_starts_with($realFile, '/var/task/public/'))
        {
            // on Vapor
            $realFile = substr($realFile, strlen('/var/task/public/'));
            if (!($content = file_get_contents($accessedFile = asset($realFile))))
                throw new NotFoundHttpException();
        } else {
            // on local Dev
            if (!File::exists($realFile)
                || !($content = File::get($realFile)))
                throw new NotFoundHttpException();
        }
    } catch (Throwable $e) {
        Log::error('File accessed via minified route not found: ' . $e->getMessage(), [
            'file' => $file,
            'realFile' => $realFile,
            'accessedFile' => $accessedFile,
            'isVapor' => $isVapor ?? false,
            'exception' => $e
        ]);

        if ($e instanceof NotFoundHttpException)
            throw $e;
        throw new NotFoundHttpException(previous: $e);
    }

    return $content;
}