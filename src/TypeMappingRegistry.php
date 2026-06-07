<?php

namespace PhpSwag;

class TypeMappingRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $mappings = [];

    public function __construct()
    {
        $this->registerBuiltInMappings();
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function register(string $class, array $schema): void
    {
        $this->mappings[$class] = $schema;
    }

    public function has(string $class): bool
    {
        if (isset($this->mappings[$class])) {
            return true;
        }

        // Special handling for Uuid classes (only if class exists)
        $uuidClasses = [
            'Ramsey\Uuid\Uuid',
            'Ramsey\Uuid\UuidInterface',
            'Symfony\Component\Uid\Uuid',
        ];
        if (in_array($class, $uuidClasses)) {
            // @phpstan-ignore-next-line
            return class_exists($class) || interface_exists($class);
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $class): ?array
    {
        if (isset($this->mappings[$class])) {
            return $this->mappings[$class];
        }

        $uuidClasses = [
            'Ramsey\Uuid\Uuid',
            'Ramsey\Uuid\UuidInterface',
            'Symfony\Component\Uid\Uuid',
        ];
        // @phpstan-ignore-next-line
        if (in_array($class, $uuidClasses) && (class_exists($class) || interface_exists($class))) {
            return ['type' => 'string', 'format' => 'uuid'];
        }

        return null;
    }

    private function registerBuiltInMappings(): void
    {
        // DateTime mappings
        $this->mappings[\DateTimeInterface::class] = ['type' => 'string', 'format' => 'date-time'];
        $this->mappings[\DateTime::class] = ['type' => 'string', 'format' => 'date-time'];
        $this->mappings[\DateTimeImmutable::class] = ['type' => 'string', 'format' => 'date-time'];

        // UploadedFile mappings
        $this->mappings['Symfony\Component\HttpFoundation\File\UploadedFile'] = [
            'type' => 'string',
            'format' => 'binary',
        ];
        $this->mappings['Psr\Http\Message\UploadedFileInterface'] = [
            'type' => 'string',
            'format' => 'binary',
        ];
        $this->mappings['Illuminate\Http\UploadedFile'] = [
            'type' => 'string',
            'format' => 'binary',
        ];
    }
}
