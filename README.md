Rewrite Register
================

Overview
--------

This is an initial proof-of-concept for a new way to handle rewrite rules in
WordPress.

Presently, rewrite rules are cached in an option. When rules change, this cache
must be "flushed", which is to say, regenerated. This is an imperfect, error-
prone, and relatively manual process -- there's no great way to tell WordPress
that rules have been added, modified, or removed, and they should be regenerated
(but only once).

This plugin aims to resolve the pains of rewrite flushing by adding a way to
register rewrite rules. On every request, the register is then checked for
changes against the most recent version. If any changes are found, the rules
regenerate. The register contains the name of the rule set, the version of those
rules, and the relative order in which they should be added.

Rule ordering is another pain point of the current rewrite system. When adding
rules via `add_rewrite_rule()`, the only options for ordering are "top" or
"bottom", and both of which are relative to the time the code is executed. When
adding rules via `add_permastruct()`, one has virtually no control over where
these rules are added. The rule ordering in this plugin is operational, but only
with regards to other rules added to the register. Unfortunately, rules cannot
be added relative to core rules at this time.

Examples
--------

To register a rule, you would call:

```php
wp_register_rewrites( $name, $version = null, $after = 'bottom' );
```

`$name` is a unique reference key (slug) which is used in the action fired when
rules are generated, and it can be used in other rule registrations to indicate
placement.

`$version` is a version identifier. This will most often be an integer, but it
could also be a float (e.g. 1.2) or a string (e.g. 'rc3'). The version exists to
tell WordPress that the rules under $name have changed.

`$after` is used to indicate the placement for your rewrite rules. This can be
'top', 'bottom', or the `$name` of any other registered rewrites.

Let's look at a very simple example of registering rules:

```php
add_action( 'register_rewrites', function(){
	wp_register_rewrites( 'demo-1', '1.1', 'demo-2' );
	wp_register_rewrites( 'demo-2', '1.2', 'demo-3' );
	wp_register_rewrites( 'demo-3', '1.0', 'top' );
} );

add_action( 'build_rewrite_rules_demo-1', function() {
	add_rewrite_rule( 'foo/?$', 'index.php?foo=1' );
} );
add_action( 'build_rewrite_rules_demo-2', function() {
	add_rewrite_rule( 'bar/?$', 'index.php?bar=all' );
	add_rewrite_rule( 'bar/(\d+)/?$', 'index.php?bar=$matches[1]' );
} );
add_action( 'build_rewrite_rules_demo-3', function() {
	add_rewrite_rule( 'bat/([^/]+)/?$', 'index.php?bat=$matches[1]' );
	add_rewrite_rule( 'bat/([^/]+)/(\d+)/?$', 'index.php?bat=$matches[1]&paged=$matches[2]' );
} );
```

First, we see that we're hooking into a new action, "register_rewrites", which
is when all rules are registered. In our function tied to "register_rewrites",
we're registering three sets of rules, `demo-1`, `demo-2`, and `demo-3`.
`demo-1` is at version 1.1 and should come after `demo-2`. `demo-2` is at
version 1.2 and should come after `demo-3`. `demo-3` is at version 1.0 and
should appear in the "top" set of rules. Breaking this all down, we should see
the rules in the "top" register in the order `demo-3`, `demo-2`, `demo-1`.

Below the "register_rewrites" action, we're hooking into three different actions
for our three sets of rules. The actions are `build_rewrite_rules_{$name}` (e.g.
"build_rewrite_rules_demo-1"). These actions only fire when the rules need to be
regenerated; they do not fire on every request. Within the functions for each of
these actions, we're simple adding the rules for that rule set.

If we changed the rule associated with `demo-3`, we would want to increase the
version, say to '1.1'. This change tells WordPress that it needs to regenerate
the rules, and it will do so on the next page request.

What's Next
-----------

This POC already relieves (in most situations) one of WordPress' major pain-
points, which is that rewrite rules do not automatically regenerate when changes
are made. It also lays out a framework for a significant improvement in the
control of rule ordering (or more accurately, rule _hierarchy_). Changes would
need to be made to core to fully realize the idea of rewrite registration.