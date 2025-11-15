<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

final class MakeActionCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'make:action';

    /**
     * @var string
     */
    protected $description = 'Create a new action class';

    /**
     * @var string
     */
    protected $type = 'Action';

    public function handle(): bool|null|int
    {
        if ($this->alreadyExists($this->getNameInput())) {
            $this->error($this->type.' already exists!');

            return 1;
        }

        return parent::handle();
    }

    protected function getNameInput(): string
    {
        /** @var string $name */
        $name = $this->argument('name');

        return Str::of(mb_trim($name))
            ->replaceEnd('.php', '')
            ->replaceEnd('Action', '')
            ->append('Action')
            ->toString();
    }

    protected function getStub(): string
    {
        return $this->laravel->basePath('stubs/action.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string // @pest-ignore-type
    {
        return $rootNamespace.'\Actions';
    }

    /**
     * @param  string  $name
     */
    protected function getPath($name): string // @pest-ignore-type
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return app_path(str_replace('\\', '/', $name).'.php');
    }
}
