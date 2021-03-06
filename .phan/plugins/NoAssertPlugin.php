<?php declare(strict_types=1);

use Phan\PluginV2;
use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use ast\Node;

/**
 * This plugin checks for occurrences of `assert(cond)` for Phan's self-analysis.
 * It is not suitable for some projects.
 * See https://github.com/phan/phan/issues/288
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * NoAssertPlugin hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class NoAssertPlugin extends PluginV2 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return NoAssertVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class NoAssertVisitor extends PluginAwarePostAnalysisVisitor
{

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     * @override
     */
    public function visitCall(Node $node)
    {
        $name = $node->children['expr']->children['name'] ?? null;
        if (!is_string($name)) {
            return;
        }
        if (strcasecmp($name, 'assert') !== 0) {
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginNoAssert',
            'assert() is discouraged. Although phan supports using assert() for type annotations, PHP\'s documentation recommends assertions only for debugging, and assert() has surprising behaviors.',
            []
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new NoAssertPlugin();
