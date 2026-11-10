<?php

namespace Omnidrive;

use Omnidrive\Exception\InvalidConfigException;

/**
 * A drive's configuration while its factory builds it: the options given, the
 * factory's defaults under them, and the keys that must be filled. Options come
 * as configuration and environment variables give them: strings too.
 *
 * omnisense/contracts' Config under the family's prefix ("omnidrive.factory_name",
 * "omnidrive.required_options"...) and its exception: defaults(), get(), string(),
 * int() and key() are the kernel's; the drive's wording of a missing option, its
 * booleans with a default, its octal permissions and its lists are omnidrive's.
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    public const PREFIX = 'omnidrive';

    /** @param list<string> $keys */
    public function validateNotEmpty(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key) => !isset($this[$key]) || '' === $this[$key] || [] === $this[$key]));
        if ($missing) {
            throw new InvalidConfigException(\sprintf('The "%s" drive needs: %s.', $this->string('omnidrive.factory_name') ?? '?', implode(', ', $missing)));
        }
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

    /** omnidrive's InvalidConfigException, for what the kernel's methods refuse (a "policy" or "usage" that is no map). */
    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
