<?php declare(strict_types=1);

namespace SOZO\Router;

/**
 * Router. Add arbitrary routing points. Instantiate on init.
 *
 * @example add_action( 'init', function() {
 *    new Plain_Route( 'stripe(/)?', [
 *        'rewrite' => 'p=123',
 *        'pre_get_posts' => function( $query ) {
 *            if( $query->is_main_query() ) {
 *                $query->set('stripe', true);
 *            }
 *        }
 *    ]);
 * });
 */
class WordPressRoute
{
    /** @var string The rule against which we match a request */
    private $regex;
    /** @var array Callbacks for the matched rule */
    private $args;
    /** @var array Names for query vars required */
    private $names;

    /**
     * Send in a regex, eg 'myroute/?$', plus args for callbacks, and an array of query vars used
     *
     * @param string $regex the rewrite regex
     * @param array $args see below
     * @param array $names query vars to add
     */
    function __construct(string $regex, array $args, array $names = [])
    {
        $this->names = $names;
        $this->args = $args;
        $this->regex = $regex;
        add_action('rewrite_rules_array', function ($rules) use ($regex, $args) {
            $new_rules = [];
            $new_rules[$regex] = 'index.php?' . (empty($args['rewrite']) ? '' : $args['rewrite']);
            return $new_rules + $rules;
        });
        add_action('query_vars', function ($vars) use ($names) {
            foreach ($names as $name) {
                $vars[] = $name;
            }
            return $vars;
        });
        add_filter('parse_request', [&$this, 'try_match']);

        return;
    }

    public function get_regex(): string
    {
        return $this->regex;
    }

    public function try_match($request): void
    {
        if ($this->regex == $request->matched_rule) {
            $this->engage_callbacks();
        }

        return;
    }

    private function engage_callbacks(): void
    {
        $hooks = [
          'pre_get_posts',
          'wp_title',
          'wp',
          'template',
        ];
        foreach ($hooks as $hook) {
            if (isset($this->args[$hook])) {
                add_filter($hook, [&$this, $hook]);
            }
        }
        /**
         * Template is a little different
         */
        if (isset($this->args['template'])) {
            add_filter('template', [&$this, 'template'], 1, 0);
        }
        do_action('plain_routes', $this->args);

        return;
    }

    public function template(): void
    {
        /** @noinspection PhpIncludeInspection */
        include locate_template($this->args['template']);
        exit;
    }

    public function __call($method, $args)
    {
        call_user_func_array($this->args[$method], $args);
    }
}
