<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class ThemeApiTest extends TestCase
{
    public function testInheritedThemeFunctionNamesAndParametersRemainCompatible(): void
    {
        $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/theme-api.json'), true, 512, JSON_THROW_ON_ERROR);
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();
        $printer = new Standard();
        $functions = [];
        foreach (['plog-includes/plog-functions.php', 'plogger.php'] as $file) {
            foreach ($finder->findInstanceOf($parser->parse(file_get_contents(dirname(__DIR__) . '/' . $file)), Function_::class) as $function) {
                $functions[$function->name->toString()] = $function;
            }
        }
        foreach ($expected as $name => $parameters) {
            self::assertArrayHasKey($name, $functions);
            foreach ($parameters as $index => [$parameterName, $default]) {
                $parameter = $functions[$name]->params[$index];
                self::assertSame($parameterName, $parameter->var->name, $name);
                self::assertSame($default, $parameter->default ? $printer->prettyPrintExpr($parameter->default) : null, $name);
            }
            // Additive optional parameters (run_query bindings) preserve old callers.
            foreach (array_slice($functions[$name]->params, count($parameters)) as $parameter) {
                self::assertNotNull($parameter->default, $name);
            }
        }
    }
}
