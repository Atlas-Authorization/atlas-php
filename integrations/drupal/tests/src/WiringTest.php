<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Tests;

use Drupal\atlas_auth\Controller\LoginController;
use Drupal\atlas_auth\Core\UserStore;
use Drupal\atlas_auth\Form\SettingsForm;
use Drupal\atlas_auth\Integration\DrupalUserStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The Drupal-coupled shell cannot run without a Drupal site, so this checks what can be checked
 * without one: every class the YAML wiring names exists and loads against Drupal core, the store
 * implements the seam, and the config schema matches the install defaults.
 */
final class WiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return array<string,mixed> */
    private function yaml(string $file): array
    {
        return Yaml::parseFile(self::ROOT . '/' . $file);
    }

    public function testRoutedControllersAndFormsExist(): void
    {
        foreach ($this->yaml('atlas_auth.routing.yml') as $name => $route) {
            $defaults = $route['defaults'];
            if (isset($defaults['_controller'])) {
                [$class, $method] = explode('::', $defaults['_controller']);
                $class = ltrim($class, '\\');
                $this->assertTrue(class_exists($class), "$name: $class");
                $this->assertTrue(method_exists($class, $method), "$name: $class::$method");
            }
            if (isset($defaults['_form'])) {
                $this->assertTrue(class_exists(ltrim($defaults['_form'], '\\')), "$name form");
            }
        }
        $this->assertTrue(class_exists(LoginController::class));
        $this->assertTrue(class_exists(SettingsForm::class));
    }

    public function testServicesPointAtRealClasses(): void
    {
        foreach ($this->yaml('atlas_auth.services.yml')['services'] as $name => $service) {
            $this->assertTrue(class_exists($service['class']), "$name: {$service['class']}");
        }
        $this->assertTrue(is_subclass_of(DrupalUserStore::class, UserStore::class));
    }

    public function testRoutesAreProtected(): void
    {
        $routes = $this->yaml('atlas_auth.routing.yml');
        $this->assertSame('administer atlas_auth', $routes['atlas_auth.settings']['requirements']['_permission']);
        $permissions = $this->yaml('atlas_auth.permissions.yml');
        $this->assertArrayHasKey('administer atlas_auth', $permissions);
        $this->assertTrue($permissions['administer atlas_auth']['restrict access']);
        // The login endpoints are for visitors who are not logged in, and must never be cached.
        foreach (['atlas_auth.start', 'atlas_auth.callback'] as $name) {
            $this->assertSame('FALSE', $routes[$name]['requirements']['_user_is_logged_in']);
            $this->assertTrue($routes[$name]['options']['no_cache']);
        }
    }

    public function testSchemaCoversEveryInstallDefault(): void
    {
        $defaults = array_keys($this->yaml('config/install/atlas_auth.settings.yml'));
        $schema = array_keys($this->yaml('config/schema/atlas_auth.schema.yml')['atlas_auth.settings']['mapping']);
        sort($defaults);
        sort($schema);
        $this->assertSame($defaults, $schema);
    }

    public function testEveryPhpFileLints(): void
    {
        $files = array_merge(glob(self::ROOT . '/src/*/*.php') ?: [], [self::ROOT . '/atlas_auth.module']);
        $this->assertNotSame([], $files);
        foreach ($files as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
            $this->assertSame(0, $code, $file . ': ' . implode("\n", $out));
        }
    }
}
