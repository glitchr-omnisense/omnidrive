<?php

namespace Omnidrive;

use Omnidrive\Exception\InvalidConfigException;

/**
 * A drive's configuration while its factory builds it: the options given, the
 * factory's defaults under them, and the keys that must be filled. Options come
 * as configuration and environment variables give them: strings too.
 *
 * @extends \ArrayObject<string, mixed>
 */
final class Config extends \ArrayObject
{
    /**
     * Sets each key not set yet.
     *
     * @param array<string, mixed> $defaults
     */
    public function defaults(array $defaults): self
    {
        foreach ($defaults as $key => $value) {
            if (!$this->offsetExists($key)) {
                $this[$key] = $value;
            }
        }

        return $this;
    }

    /** @param list<string> $keys */
    public function validateNotEmpty(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key) => !isset($this[$key]) || '' === $this[$key] || [] === $this[$key]));
        if ($missing) {
            throw new InvalidConfigException(\sprintf('The "%s" drive needs: %s.', $this->string('omnidrive.factory_name') ?? '?', implode(', ', $missing)));
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->offsetExists($key) ? $this[$key] : $default;
    }

    /** A string option; null when it is empty. */
    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** An octal permission, as an integer (0644) or a string ("0644", "644"). */
    public function octal(string $key, int $default): int
    {
        $value = $this->get($key);

        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && '' !== trim($value) && ctype_digit(trim($value)) => (int) octdec(trim($value)),
            default => $default,
        };
    }

    /** A boolean option, as an environment variable gives it too: "1", "true", "yes", "on". */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if (null === $value || '' === $value) {
            return $default;
        }

        return \is_bool($value) ? $value : \in_array(strtolower(trim(\is_scalar($value) ? (string) $value : '')), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * A list given as one environment variable arrives as a single comma-separated
     * string: split here.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        $list = [];
        $value = $this->get($key, []);
        foreach (\is_array($value) ? $value : [$value] as $item) {
            foreach (explode(',', \is_scalar($item) ? (string) $item : '') as $one) {
                if ('' !== trim($one)) {
                    $list[] = trim($one);
                }
            }
        }

        return $list;
    }
}
