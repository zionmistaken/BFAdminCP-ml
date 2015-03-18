<?php

$url = 'https://github.com/Prophet731/BFAdminCP/archive/v2.0.0-rc.2.zip';
$filepath = getcwd() . DIRECTORY_SEPARATOR . 'bfacpv2rc2.zip';

function directoryDel($dir) {
    if( ! file_exists($dir) ) {
        return;
    }
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach($files as $file) {
        if ( $file->isDir()  ) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($dir);
}

function cleanup($fresh = false) {
    global $filepath;

    if($fresh) {
        directoryDel(getcwd() . DIRECTORY_SEPARATOR . 'BFAdminCP-2.0.0-rc.2');
        directoryDel(getcwd() . DIRECTORY_SEPARATOR . 'bfacpv2rc2');
    }

    if(file_exists($filepath)) {
        unlink($filepath);
    }
}

function previousInstallExists() {
    if( ! file_exists(getcwd() . DIRECTORY_SEPARATOR . 'BFAdminCP-2.0.0-rc.2' . DIRECTORY_SEPARATOR)) {
        return true;
    }

    if( ! file_exists(getcwd() . DIRECTORY_SEPARATOR . 'bfacpv2rc2' . DIRECTORY_SEPARATOR)) {
        return true;
    }

    return false;
}

try {

    if( ! is_writable(getcwd())) {
        throw new Exception(sprintf("%s is not writeable.", getcwd()));
    }

    if(isset($_GET['fresh']) && $_GET['fresh'] == 1) {
        cleanup(true);
    }

    if( ! file_exists($filepath)) {
        file_put_contents($filepath, file_get_contents($url));
    }

    if(file_exists($filepath)) {

        if(class_exists('ZipArchive')) {
            $zip = new ZipArchive;

            if($zip->open($filepath) === true) {

                if( previousInstallExists() ) {
                    $zip->extractTo('./');
                    $zip->close();
                    cleanup();
                    rename('BFAdminCP-2.0.0-rc.2', 'bfacpv2rc2');
                } else {
                    throw new Exception("Found previous installation. Aborting...");
                }

            } else {
                throw new Exception("Unable to extract archive. Please manually install the application.");
            }

        } else {
            cleanup();
            throw new Exception("Unable to instantiate class ZipArchive. Please manually install the application.");
        }

    }
} catch(Exception $e) {
    echo $e->getMessage();
}
