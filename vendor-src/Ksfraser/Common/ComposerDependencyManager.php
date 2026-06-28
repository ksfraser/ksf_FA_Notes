<?php

namespace Ksfraser\Common;

class ComposerDependencyManager
{
    private $moduleDir;
    private $composerJsonPath;
    private $autoloadPath;
    private $config;
    
    public function __construct($moduleDir, $config = [])
    {
        $this->moduleDir = rtrim($moduleDir, '/\\');
        $this->composerJsonPath = $this->moduleDir . DIRECTORY_SEPARATOR . 'composer.json';
        $this->autoloadPath = $this->moduleDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        
        $this->config = array_merge([
            'dry_run' => false,
            'dev_dependencies' => false,
            'timeout' => 300,
        ], $config);
    }
    
    public function ensureDependencies(): bool
    {
        if (!$this->hasComposerJson()) {
            return true;
        }
        if ($this->isInstalled()) {
            return true;
        }
        $this->install();
        return true;
    }
    
    public function hasComposerJson(): bool
    {
        return file_exists($this->composerJsonPath);
    }
    
    public function isInstalled(): bool
    {
        $hasAutoload = file_exists($this->autoloadPath);
        $hasLock = file_exists($this->moduleDir . DIRECTORY_SEPARATOR . 'composer.lock');
        return $hasAutoload && $hasLock;
    }
    
    public function getAutoloadPath(): string
    {
        if (!file_exists($this->autoloadPath)) {
            throw new \Exception(
                'Composer autoloader not found. Run: composer install in ' . $this->moduleDir
            );
        }
        return $this->autoloadPath;
    }
    
    public function install(): string
    {
        if (!$this->canExecuteShellCommands()) {
            throw new \Exception(
                'Cannot run shell commands (shell_exec disabled). ' .
                'Please manually run: cd ' . $this->moduleDir . ' && composer install'
            );
        }
        
        $command = $this->buildComposerCommand();
        \error_log('COMPOSER: Running: ' . $command);
        
        $output = shell_exec($command);
        
        if ($output === null) {
            throw new \Exception(
                'Composer command failed or returned null. ' .
                'Make sure composer is installed and in PATH. ' .
                'Fallback: cd ' . $this->moduleDir . ' && composer install'
            );
        }
        
        \error_log('COMPOSER: Output: ' . $output);
        
        if (!file_exists($this->autoloadPath)) {
            throw new \Exception(
                'Composer installation failed: vendor/autoload.php not found after running composer install.' .
                "\nOutput: " . $output
            );
        }
        
        return $output;
    }
    
    private function buildComposerCommand(): string
    {
        $flags = [];
        if (!$this->config['dev_dependencies']) {
            $flags[] = '--no-dev';
        }
        
        $cmd = 'composer install ' . implode(' ', $flags);
        $cmd .= ' --working-dir=' . escapeshellarg($this->moduleDir) . ' 2>&1';
        
        if (PHP_OS_FAMILY !== 'Windows') {
            $cmd = 'which composer >/dev/null 2>&1 && ' . $cmd . ' || composer ' . 
                   implode(' ', $flags) . ' --working-dir=' . escapeshellarg($this->moduleDir) . ' 2>&1';
        }
        
        return $cmd;
    }
    
    private function canExecuteShellCommands(): bool
    {
        return function_exists('shell_exec') && function_exists('exec');
    }
    
    public function getStatus(): array
    {
        return [
            'module_dir' => $this->moduleDir,
            'composer_json_exists' => $this->hasComposerJson(),
            'autoload_path' => $this->autoloadPath,
            'autoload_exists' => file_exists($this->autoloadPath),
            'composer_lock_exists' => file_exists($this->moduleDir . DIRECTORY_SEPARATOR . 'composer.lock'),
            'is_installed' => $this->isInstalled(),
            'shell_exec_available' => $this->canExecuteShellCommands(),
        ];
    }
}