<?php
/**
 * Rewrite API: WP_Rewrite class
 *
 * @package WordPress
 * @subpackage Rewrite
 * @since 1.5.0
 */
/***
作用? 主要是用来解析美化过后的url、
rewrite rule和permalink之间的关系?
rewrite rule更底层些, 大多数rule是根据permalink自动生成的

产生美化url时(参考函数get_category_link(), get_permalink(), ...), 并未直接利用rewrite rule, 而是利用的permalink(即固定链接结构串)，简单替换相应的模板变量就OK，
解析美化url时, 是要用到rewrite rule的, 但rewrite rule中很多记录来源于permalink

*/
/**
 * Core class used to implement a rewrite component API.
 *
 * The WordPress Rewrite class writes the rewrite module rules to the .htaccess
 * file. It also handles parsing the request to get the correct setup for the
 * WordPress Query class.
 *
 * The Rewrite along with WP class function as a front controller for WordPress.
 * You can add rules to trigger your page view and processing using this
 * component. The full functionality of a front controller does not exist,
 * meaning you can't define how the template files load based on the rewrite
 * rules.
 *
 * @since 1.5.0
 */
/**
常见rules, $this->rules, 这是打印出来的一份常见的rewrite rules
[
    ^wp-json/?$ = "index.php?rest_route=/"
    ^wp-json/(.*)? = "index.php?rest_route=/$matches[1]"
    ^index.php/wp-json/?$ = "index.php?rest_route=/"
    ^index.php/wp-json/(.*)? = "index.php?rest_route=/$matches[1]"
    
    category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?category_name=$matches[1]&feed=$matches[2]"
    category/(.+?)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?category_name=$matches[1]&feed=$matches[2]"
    category/(.+?)/embed/?$ = "index.php?category_name=$matches[1]&embed=true"
    category/(.+?)/page/?([0-9]{1,})/?$ = "index.php?category_name=$matches[1]&paged=$matches[2]"   // 比如url中是page=9,但解析后query_vars['paged']=9中
    // 这里可以看出category/xxx 实际上是取slug为category_name的, 这个是怎么来源的?
    // 对category, tag这种taxomony的rewrite则是来源于register_taxonomy(), create_initial_taxonomies()中的定义
    category/(.+?)/?$ = "index.php?category_name=$matches[1]" 
    
    tag/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?tag=$matches[1]&feed=$matches[2]"
    tag/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?tag=$matches[1]&feed=$matches[2]"
    tag/([^/]+)/embed/?$ = "index.php?tag=$matches[1]&embed=true"
    tag/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?tag=$matches[1]&paged=$matches[2]"
    tag/([^/]+)/?$ = "index.php?tag=$matches[1]"
    type/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?post_format=$matches[1]&feed=$matches[2]"
    type/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?post_format=$matches[1]&feed=$matches[2]"
    type/([^/]+)/embed/?$ = "index.php?post_format=$matches[1]&embed=true"
    type/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?post_format=$matches[1]&paged=$matches[2]"
    type/([^/]+)/?$ = "index.php?post_format=$matches[1]"
    .*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$ = "index.php?feed=old"
    .*wp-app\.php(/.*)?$ = "index.php?error=403"
    .*wp-register.php$ = "index.php?register=true"
    feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]"
    (feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]"
    embed/?$ = "index.php?&embed=true"
    page/?([0-9]{1,})/?$ = "index.php?&paged=$matches[1]"
    comments/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]&withcomments=1"
    comments/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]&withcomments=1"
    comments/embed/?$ = "index.php?&embed=true"
    search/(.+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?s=$matches[1]&feed=$matches[2]"
    search/(.+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?s=$matches[1]&feed=$matches[2]"
    search/(.+)/embed/?$ = "index.php?s=$matches[1]&embed=true"
    search/(.+)/page/?([0-9]{1,})/?$ = "index.php?s=$matches[1]&paged=$matches[2]"
    search/(.+)/?$ = "index.php?s=$matches[1]"
    author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?author_name=$matches[1]&feed=$matches[2]"
    author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?author_name=$matches[1]&feed=$matches[2]"
    author/([^/]+)/embed/?$ = "index.php?author_name=$matches[1]&embed=true"
    author/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?author_name=$matches[1]&paged=$matches[2]"
    author/([^/]+)/?$ = "index.php?author_name=$matches[1]"
    ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]"
    ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]"
    ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&embed=true"
    ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]"
    ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]"
    ([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]"
    ([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]"
    ([0-9]{4})/([0-9]{1,2})/embed/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&embed=true"
    ([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]"
    ([0-9]{4})/([0-9]{1,2})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]"
    ([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&feed=$matches[2]"
    ([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&feed=$matches[2]"
    ([0-9]{4})/embed/?$ = "index.php?year=$matches[1]&embed=true"
    ([0-9]{4})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&paged=$matches[2]"
    ([0-9]{4})/?$ = "index.php?year=$matches[1]"
    .?.+?/attachment/([^/]+)/?$ = "index.php?attachment=$matches[1]"
    .?.+?/attachment/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
    .?.+?/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    .?.+?/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    .?.+?/attachment/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
    .?.+?/attachment/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
    (.?.+?)/embed/?$ = "index.php?pagename=$matches[1]&embed=true"
    (.?.+?)/trackback/?$ = "index.php?pagename=$matches[1]&tb=1"
    (.?.+?)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?pagename=$matches[1]&feed=$matches[2]"
    (.?.+?)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?pagename=$matches[1]&feed=$matches[2]"
    (.?.+?)/page/?([0-9]{1,})/?$ = "index.php?pagename=$matches[1]&paged=$matches[2]"
    (.?.+?)/comment-page-([0-9]{1,})/?$ = "index.php?pagename=$matches[1]&cpage=$matches[2]"
    (.?.+?)(?:/([0-9]+))?/?$ = "index.php?pagename=$matches[1]&page=$matches[2]"
    [^/]+/attachment/([^/]+)/?$ = "index.php?attachment=$matches[1]"
    [^/]+/attachment/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
    [^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    [^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    [^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
    [^/]+/attachment/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
    ([^/]+)/embed/?$ = "index.php?name=$matches[1]&embed=true"
    ([^/]+)/trackback/?$ = "index.php?name=$matches[1]&tb=1"
    ([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?name=$matches[1]&feed=$matches[2]"
    ([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?name=$matches[1]&feed=$matches[2]"
    ([^/]+)/page/?([0-9]{1,})/?$ = "index.php?name=$matches[1]&paged=$matches[2]"
    ([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?name=$matches[1]&cpage=$matches[2]"
    ([^/]+)(?:/([0-9]+))?/?$ = "index.php?name=$matches[1]&page=$matches[2]"
    [^/]+/([^/]+)/?$ = "index.php?attachment=$matches[1]"
    [^/]+/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
    [^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    [^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
    [^/]+/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
    [^/]+/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
]
*/		
 
class WP_Rewrite {
        /***
        post, author, category等所有的permalink都在此类的成员变量中
        */
        
	/**
	 * Permalink structure for posts.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	 /* post的固定链接结构(为空表示是朴素模式, 否则是美化模式) , init()时会从db中取出其值*/
	public $permalink_structure;

	/**
	 * Whether to add trailing slashes.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	public $use_trailing_slashes;

	/**
	 * Base for the author permalink structure (example.com/$author_base/authorname).
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $author_base = 'author';

	/**
	 * Permalink structure for author archives.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	 /*** 查author的permalink时, 优先看author_structure, 如果无值再使用$author_base/%author%/  */
	var $author_structure;

	/**
	 * Permalink structure for date archives.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $date_structure;

	/**
	 * Permalink structure for pages.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	 /*** 
	 除了post, 
	 page页面也有固定链接结构, 当美化模板开启后才有效，默认为%pagename%, 但是可以在代码中修改
	 比如要在页面后添加html后缀,方法如下
	 $wp_rewrite->page_structure = $wp_rewrite->page_structure . '.html';
	 */
	var $page_structure;

	/**
	 * Base of the search permalink structure (example.com/$search_base/query).
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $search_base = 'search';

	/**
	 * Permalink structure for searches.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $search_structure;

	/**
	 * Comments permalink base.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $comments_base = 'comments';

	/**
	 * Pagination permalink base.
	 *
	 * @since 3.1.0
	 * @var string
	 */
	public $pagination_base = 'page';

	/**
	 * Comments pagination permalink base.
	 *
	 * @since 4.2.0
	 * @access private
	 * @var string
	 */
	var $comments_pagination_base = 'comment-page';

	/**
	 * Feed permalink base.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $feed_base = 'feed';

	/**
	 * Comments feed permalink structure.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $comment_feed_structure;

	/**
	 * Feed request permalink structure.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $feed_structure;

	/**
	 * The static portion of the post permalink structure.
	 *
	 * If the permalink structure is "/archive/%post_id%" then the front
	 * is "/archive/". If the permalink structure is "/%year%/%postname%/"
	 * then the front is "/".
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 *
	 * @see WP_Rewrite::init()
	 */
	public $front;

	/**
	 * The prefix for all permalink structures.
	 *
	 * If PATHINFO/index permalinks are in use then the root is the value of
	 * `WP_Rewrite::$index` with a trailing slash appended. Otherwise the root
	 * will be empty.
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 *
	 * @see WP_Rewrite::init()
	 * @see WP_Rewrite::using_index_permalinks()
	 */
	public $root = '';

	/**
	 * The name of the index file which is the entry point to all requests.
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $index = 'index.php';

	/**
	 * Variable name to use for regex matches in the rewritten query.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var string
	 */
	var $matches = '';

	/**
	 * Rewrite rules to match against the request to find the redirect or query.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	var $rules;

	/**
	 * Additional rules added external to the rewrite class.
	 *
	 * Those not generated by the class, see add_rewrite_rule().
	 *
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	var $extra_rules = array();

	/**
	 * Additional rules that belong at the beginning to match first.
	 *
	 * Those not generated by the class, see add_rewrite_rule().
	 *
	 * @since 2.3.0
	 * @access private
	 * @var array
	 */
	var $extra_rules_top = array();

	/**
	 * Rules that don't redirect to WordPress' index.php.
	 *
	 * These rules are written to the mod_rewrite portion of the .htaccess,
	 * and are added by add_external_rule().
	 *
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	var $non_wp_rules = array();

	/**
	 * Extra permalink structures, e.g. categories, added by add_permastruct().
	 *
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	 /**
	 post之类的是从db中取出后放在permalink_structure变量中
	 而category之类的permalink直接存在此处内存变量中
	 */
	var $extra_permastructs = array();

	/**
	 * Endpoints (like /trackback/) added by add_rewrite_endpoint().
	 *
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	var $endpoints;

	/**
	 * Whether to write every mod_rewrite rule for WordPress into the .htaccess file.
	 *
	 * This is off by default, turning it on might print a lot of rewrite rules
	 * to the .htaccess file.
	 *
	 * @since 2.0.0
	 * @access public
	 * @var bool
	 *
	 * @see WP_Rewrite::mod_rewrite_rules()
	 */
	public $use_verbose_rules = false;

	/**
	 * Could post permalinks be confused with those of pages?
	 *
	 * If the first rewrite tag in the post permalink structure is one that could
	 * also match a page name (e.g. %postname% or %author%) then this flag is
	 * set to true. Prior to WordPress 3.3 this flag indicated that every page
	 * would have a set of rules added to the top of the rewrite rules array.
	 * Now it tells WP::parse_request() to check if a URL matching the page
	 * permastruct is actually a page before accepting it.
	 *
	 * @since 2.5.0
	 * @access public
	 * @var bool
	 *
	 * @see WP_Rewrite::init()
	 */
	public $use_verbose_page_rules = true;

	/**
	 * Rewrite tags that can be used in permalink structures.
	 *
	 * These are translated into the regular expressions stored in
	 * `WP_Rewrite::$rewritereplace` and are rewritten to the query
	 * variables listed in WP_Rewrite::$queryreplace.
	 *
	 * Additional tags can be added with add_rewrite_tag().
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	 /** 这是预定义的rewrite rule token 模板变量 , 调用add_rewrite_tag()其实就是往这三个数组内append内容
	 
        add_rewrite_tag( '%pagename%', '(.?.+?)', 'pagename=' );
        add_rewrite_tag( "%$post_type%", '([^/]+)', $args->query_var ? "{$args->query_var}=" : "post_type=$post_type&name=" );
        add_rewrite_tag( "%$post_type%", '(.+?)', $args->query_var ? "{$args->query_var}=" : "post_type=$post_type&pagename=" );
        add_rewrite_tag( "%$taxonomy%", $tag, $args['query_var'] ? "{$args['query_var']}=" : "taxonomy=$taxonomy&term=" );
        %category%是在哪定义的?
	 */
	var $rewritecode = array(
		'%year%',
		'%monthnum%',
		'%day%',
		'%hour%',
		'%minute%',
		'%second%',
		'%postname%',  // title? 还是slug? 应当是slug
		'%post_id%',
		'%author%',       // author name
		'%pagename%',   // page 的slug?
		'%search%'
	);

	/**
	 * Regular expressions to be substituted into rewrite rules in place
	 * of rewrite tags, see WP_Rewrite::$rewritecode.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	 /** 这是上面模板变量对应的正则 */
	var $rewritereplace = array(
		'([0-9]{4})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([0-9]{1,2})',
		'([^/]+)',
		'([0-9]+)',
		'([^/]+)',
		'([^/]+?)',
		'(.+)'
	);

	/**
	 * Query variables that rewrite tags map to, see WP_Rewrite::$rewritecode.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	 /** 这是上面模板变量对应的解析, 解析出来的变量以year=1900的形式放在url 参数中 */
	var $queryreplace = array(
		'year=',
		'monthnum=',
		'day=',
		'hour=',
		'minute=',
		'second=',
		'name=',
		'p=',
		'author_name=',
		'pagename=',
		's='
	);

	/**
	 * Supported default feeds.
	 *
	 * @since 1.5.0
	 * @var array
	 */
	public $feeds = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );

	/**
	 * Determines whether permalinks are being used.
	 *
	 * This can be either rewrite module or permalink in the HTTP query string.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return bool True, if permalinks are enabled.
	 */
	public function using_permalinks() {
		return ! empty($this->permalink_structure);
	}

	/**
	 * Determines whether permalinks are being used and rewrite module is not enabled.
	 *
	 * Means that permalink links are enabled and index.php is in the URL.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return bool Whether permalink links are enabled and index.php is in the URL.
	 */
	public function using_index_permalinks() {
		if ( empty( $this->permalink_structure ) ) {
			return false;
		}

		// If the index is not in the permalink, we're using mod_rewrite.
		return preg_match( '#^/*' . $this->index . '#', $this->permalink_structure );
	}

	/**
	 * Determines whether permalinks are being used and rewrite module is enabled.
	 *
	 * Using permalinks and index.php is not in the URL.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return bool Whether permalink links are enabled and index.php is NOT in the URL.
	 */
	public function using_mod_rewrite_permalinks() {
		return $this->using_permalinks() && ! $this->using_index_permalinks();
	}

	/**
	 * Indexes for matches for usage in preg_*() functions.
	 *
	 * The format of the string is, with empty matches property value, '$NUM'.
	 * The 'NUM' will be replaced with the value in the $number parameter. With
	 * the matches property not empty, the value of the returned string will
	 * contain that value of the matches property. The format then will be
	 * '$MATCHES[NUM]', with MATCHES as the value in the property and NUM the
	 * value of the $number parameter.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @param int $number Index number.
	 * @return string
	 */
	public function preg_index($number) {
		$match_prefix = '$';
		$match_suffix = '';

		if ( ! empty($this->matches) ) {
			$match_prefix = '$' . $this->matches . '[';
			$match_suffix = ']';
		}

		return "$match_prefix$number$match_suffix";
	}

	/**
	 * Retrieves all page and attachments for pages URIs.
	 *
	 * The attachments are for those that have pages as parents and will be
	 * retrieved.
	 *
	 * @since 2.5.0
	 * @access public
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array Array of page URIs as first element and attachment URIs as second element.
	 */
	public function page_uri_index() {
		global $wpdb;

		// Get pages in order of hierarchy, i.e. children after parents.
		$pages = $wpdb->get_results("SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'page' AND post_status != 'auto-draft'");
		$posts = get_page_hierarchy( $pages );

		// If we have no pages get out quick.
		if ( !$posts )
			return array( array(), array() );

		// Now reverse it, because we need parents after children for rewrite rules to work properly.
		$posts = array_reverse($posts, true);

		$page_uris = array();
		$page_attachment_uris = array();

		foreach ( $posts as $id => $post ) {
			// URL => page name
			$uri = get_page_uri($id);
			$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = %d", $id ));
			if ( !empty($attachments) ) {
				foreach ( $attachments as $attachment ) {
					$attach_uri = get_page_uri($attachment->ID);
					$page_attachment_uris[$attach_uri] = $attachment->ID;
				}
			}

			$page_uris[$uri] = $id;
		}

		return array( $page_uris, $page_attachment_uris );
	}

	/**
	 * Retrieves all of the rewrite rules for pages.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return array Page rewrite rules.
	 */
	public function page_rewrite_rules() {
		// The extra .? at the beginning prevents clashes with other regular expressions in the rules array.
		$this->add_rewrite_tag( '%pagename%', '(.?.+?)', 'pagename=' );

		return $this->generate_rewrite_rules( $this->get_page_permastruct(), EP_PAGES, true, true, false, false );
	}

	/**
	 * Retrieves date permalink structure, with year, month, and day.
	 *
	 * The permalink structure for the date, if not set already depends on the
	 * permalink structure. It can be one of three formats. The first is year,
	 * month, day; the second is day, month, year; and the last format is month,
	 * day, year. These are matched against the permalink structure for which
	 * one is used. If none matches, then the default will be used, which is
	 * year, month, day.
	 *
	 * Prevents post ID and date permalinks from overlapping. In the case of
	 * post_id, the date permalink will be prepended with front permalink with
	 * 'date/' before the actual permalink to form the complete date permalink
	 * structure.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False on no permalink structure. Date permalink structure.
	 */
	public function get_date_permastruct() {
		if ( isset($this->date_structure) )
			return $this->date_structure;

		if ( empty($this->permalink_structure) ) {
			$this->date_structure = '';
			return false;
		}

		// The date permalink must have year, month, and day separated by slashes.
		$endians = array('%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%');

		$this->date_structure = '';
		$date_endian = '';

		foreach ( $endians as $endian ) {
			if ( false !== strpos($this->permalink_structure, $endian) ) {
				$date_endian= $endian;
				break;
			}
		}

		if ( empty($date_endian) )
			$date_endian = '%year%/%monthnum%/%day%';

		/*
		 * Do not allow the date tags and %post_id% to overlap in the permalink
		 * structure. If they do, move the date tags to $front/date/.
		 */
		$front = $this->front;
		preg_match_all('/%.+?%/', $this->permalink_structure, $tokens);
		$tok_index = 1;
		foreach ( (array) $tokens[0] as $token) {
			if ( '%post_id%' == $token && ($tok_index <= 3) ) {
				$front = $front . 'date/';
				break;
			}
			$tok_index++;
		}

		$this->date_structure = $front . $date_endian;

		return $this->date_structure;
	}

	/**
	 * Retrieves the year permalink structure without month and day.
	 *
	 * Gets the date permalink structure and strips out the month and day
	 * permalink structures.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return false|string False on failure. Year structure on success.
	 */
	public function get_year_permastruct() {
		$structure = $this->get_date_permastruct();

		if ( empty($structure) )
			return false;

		$structure = str_replace('%monthnum%', '', $structure);
		$structure = str_replace('%day%', '', $structure);
		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	/**
	 * Retrieves the month permalink structure without day and with year.
	 *
	 * Gets the date permalink structure and strips out the day permalink
	 * structures. Keeps the year permalink structure.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return false|string False on failure. Year/Month structure on success.
	 */
	public function get_month_permastruct() {
		$structure = $this->get_date_permastruct();

		if ( empty($structure) )
			return false;

		$structure = str_replace('%day%', '', $structure);
		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	/**
	 * Retrieves the day permalink structure with month and year.
	 *
	 * Keeps date permalink structure with all year, month, and day.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False on failure. Year/Month/Day structure on success.
	 */
	public function get_day_permastruct() {
		return $this->get_date_permastruct();
	}

	/**
	 * Retrieves the permalink structure for categories.
	 *
	 * If the category_base property has no value, then the category structure
	 * will have the front property value, followed by 'category', and finally
	 * '%category%'. If it does, then the root property will be used, along with
	 * the category_base property value.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False on failure. Category permalink structure.
	 */
	public function get_category_permastruct() {
		return $this->get_extra_permastruct('category');
	}

	/**
	 * Retrieve the permalink structure for tags.
	 *
	 * If the tag_base property has no value, then the tag structure will have
	 * the front property value, followed by 'tag', and finally '%tag%'. If it
	 * does, then the root property will be used, along with the tag_base
	 * property value.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @return string|false False on failure. Tag permalink structure.
	 */
	public function get_tag_permastruct() {
		return $this->get_extra_permastruct('post_tag');
	}

	/**
	 * Retrieves an extra permalink structure by name.
	 *
	 * @since 2.5.0
	 * @access public
	 *
	 * @param string $name Permalink structure name.
	 * @return string|false False if not found. Permalink structure string.
	 */
	public function get_extra_permastruct($name) {
	        /*** 如果是朴素模式就谈不上permalink了, 直接返回 */
		if ( empty($this->permalink_structure) )
			return false;

		if ( isset($this->extra_permastructs[$name]) )
			return $this->extra_permastructs[$name]['struct'];

		return false;
	}

	/**
	 * Retrieves the author permalink structure.
	 *
	 * The permalink structure is front property, author base, and finally
	 * '/%author%'. Will set the author_structure property and then return it
	 * without attempting to set the value again.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False if not found. Permalink structure string.
	 */
	public function get_author_permastruct() {
		if ( isset($this->author_structure) )
			return $this->author_structure;

		if ( empty($this->permalink_structure) ) {
			$this->author_structure = '';
			return false;
		}

		$this->author_structure = $this->front . $this->author_base . '/%author%';

		return $this->author_structure;
	}

	/**
	 * Retrieves the search permalink structure.
	 *
	 * The permalink structure is root property, search base, and finally
	 * '/%search%'. Will set the search_structure property and then return it
	 * without attempting to set the value again.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False if not found. Permalink structure string.
	 */
	public function get_search_permastruct() {
		if ( isset($this->search_structure) )
			return $this->search_structure;

		if ( empty($this->permalink_structure) ) {
			$this->search_structure = '';
			return false;
		}

		$this->search_structure = $this->root . $this->search_base . '/%search%';

		return $this->search_structure;
	}

	/**
	 * Retrieves the page permalink structure.
	 *
	 * The permalink structure is root property, and '%pagename%'. Will set the
	 * page_structure property and then return it without attempting to set the
	 * value again.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False if not found. Permalink structure string.
	 */
	 /***  
	 获取page的固定链接结构
	 */
	public function get_page_permastruct() {
		if ( isset($this->page_structure) )
			return $this->page_structure;

		if (empty($this->permalink_structure)) {
			$this->page_structure = '';
			return false;
		}

		$this->page_structure = $this->root . '%pagename%';

		return $this->page_structure;
	}

	/**
	 * Retrieves the feed permalink structure.
	 *
	 * The permalink structure is root property, feed base, and finally
	 * '/%feed%'. Will set the feed_structure property and then return it
	 * without attempting to set the value again.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False if not found. Permalink structure string.
	 */
	public function get_feed_permastruct() {
		if ( isset($this->feed_structure) )
			return $this->feed_structure;

		if ( empty($this->permalink_structure) ) {
			$this->feed_structure = '';
			return false;
		}

		$this->feed_structure = $this->root . $this->feed_base . '/%feed%';

		return $this->feed_structure;
	}

	/**
	 * Retrieves the comment feed permalink structure.
	 *
	 * The permalink structure is root property, comment base property, feed
	 * base and finally '/%feed%'. Will set the comment_feed_structure property
	 * and then return it without attempting to set the value again.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string|false False if not found. Permalink structure string.
	 */
	public function get_comment_feed_permastruct() {
		if ( isset($this->comment_feed_structure) )
			return $this->comment_feed_structure;

		if (empty($this->permalink_structure)) {
			$this->comment_feed_structure = '';
			return false;
		}

		$this->comment_feed_structure = $this->root . $this->comments_base . '/' . $this->feed_base . '/%feed%';

		return $this->comment_feed_structure;
	}

	/**
	 * Adds or updates existing rewrite tags (e.g. %postname%).
	 *
	 * If the tag already exists, replace the existing pattern and query for
	 * that tag, otherwise add the new tag.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @see WP_Rewrite::$rewritecode
	 * @see WP_Rewrite::$rewritereplace
	 * @see WP_Rewrite::$queryreplace
	 *
	 * @param string $tag   Name of the rewrite tag to add or update.
	 * @param string $regex Regular expression to substitute the tag for in rewrite rules.
	 * @param string $query String to append to the rewritten query. Must end in '='.
	 */
	public function add_rewrite_tag( $tag, $regex, $query ) {
		$position = array_search( $tag, $this->rewritecode );
		if ( false !== $position && null !== $position ) {
			$this->rewritereplace[ $position ] = $regex;
			$this->queryreplace[ $position ] = $query;
		} else {
			$this->rewritecode[] = $tag;
			$this->rewritereplace[] = $regex;
			$this->queryreplace[] = $query;
		}
	}


	/**
	 * Removes an existing rewrite tag.
	 *
	 * @since 4.5.0
	 * @access public
	 *
	 * @see WP_Rewrite::$rewritecode
	 * @see WP_Rewrite::$rewritereplace
	 * @see WP_Rewrite::$queryreplace
	 *
	 * @param string $tag Name of the rewrite tag to remove.
	 */
	public function remove_rewrite_tag( $tag ) {
		$position = array_search( $tag, $this->rewritecode );
		if ( false !== $position && null !== $position ) {
			unset( $this->rewritecode[ $position ] );
			unset( $this->rewritereplace[ $position ] );
			unset( $this->queryreplace[ $position ] );
		}
	}

	/**
	 * Generates rewrite rules from a permalink structure.
	 *
	 * The main WP_Rewrite function for building the rewrite rule list. The
	 * contents of the function is a mix of black magic and regular expressions,
	 * so best just ignore the contents and move to the parameters.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @param string $permalink_structure The permalink structure.
	 * @param int    $ep_mask             Optional. Endpoint mask defining what endpoints are added to the structure.
	 *                                    Accepts `EP_NONE`, `EP_PERMALINK`, `EP_ATTACHMENT`, `EP_DATE`, `EP_YEAR`,
	 *                                    `EP_MONTH`, `EP_DAY`, `EP_ROOT`, `EP_COMMENTS`, `EP_SEARCH`, `EP_CATEGORIES`,
	 *                                    `EP_TAGS`, `EP_AUTHORS`, `EP_PAGES`, `EP_ALL_ARCHIVES`, and `EP_ALL`.
	 *                                    Default `EP_NONE`.
	 * @param bool   $paged               Optional. Whether archive pagination rules should be added for the structure.
	 *                                    Default true.
	 * @param bool   $feed                Optional Whether feed rewrite rules should be added for the structure.
	 *                                    Default true.
	 * @param bool   $forcomments         Optional. Whether the feed rules should be a query for a comments feed.
	 *                                    Default false.
	 * @param bool   $walk_dirs           Optional. Whether the 'directories' making up the structure should be walked
	 *                                    over and rewrite rules built for each in-turn. Default true.
	 * @param bool   $endpoints           Optional. Whether endpoints should be applied to the generated rewrite rules.
	 *                                    Default true.
	 * @return array Rewrite rule list.
	 */
	 /***  
	 根据$permalink_structure,生成一组rules
	 返回一个[ 
	    match1 => query1,
	    match2 => query2,
	    ...
	 ]   数组
	 作用? 用来解析url中的参数

	 比如有一个permalink是hello/%aaa%/%bbb%/, 仅凭这个串是没办法生成rule的, 
	 还需要有%aaa%这个tag对应的正则(即通过add_rewrite_tag()增加的对%aaa%的定义)
	 如先把上面的替换成hello/(+.)/(+.) 就成为rule的左半部分

        比如category/%category%/如何生成下面的rewrite rule?
        category/(.+?)/?$ = "index.php?category_name=$matches[1]" 	         
	 */
	public function generate_rewrite_rules($permalink_structure, $ep_mask = EP_NONE, $paged = true, $feed = true, $forcomments = false, $walk_dirs = true, $endpoints = true) {
		// Build a regex to match the feed section of URLs, something like (feed|atom|rss|rss2)/?
		$feedregex2 = '';
		foreach ( (array) $this->feeds as $feed_name)
			$feedregex2 .= $feed_name . '|';
		$feedregex2 = '(' . trim($feedregex2, '|') . ')/?$';

		/*
		 * $feedregex is identical but with /feed/ added on as well, so URLs like <permalink>/feed/atom
		 * and <permalink>/atom are both possible
		 */
		$feedregex = $this->feed_base . '/' . $feedregex2;

		// Build a regex to match the trackback and page/xx parts of URLs.
		$trackbackregex = 'trackback/?$';
		$pageregex = $this->pagination_base . '/?([0-9]{1,})/?$';
		$commentregex = $this->comments_pagination_base . '-([0-9]{1,})/?$';
		$embedregex = 'embed/?$';

		// Build up an array of endpoint regexes to append => queries to append.
		if ( $endpoints ) {
			$ep_query_append = array ();
			foreach ( (array) $this->endpoints as $endpoint) {
				// Match everything after the endpoint name, but allow for nothing to appear there.
				$epmatch = $endpoint[1] . '(/(.*))?/?$';

				// This will be appended on to the rest of the query for each dir.
				$epquery = '&' . $endpoint[2] . '=';
				$ep_query_append[$epmatch] = array ( $endpoint[0], $epquery );
			}
		}

		// Get everything up to the first rewrite tag.
		$front = substr($permalink_structure, 0, strpos($permalink_structure, '%'));

		// Build an array of the tags (note that said array ends up being in $tokens[0]).
		/** 取出固定链接中的所有token，即以%包围的模板变量 */
		preg_match_all('/%.+?%/', $permalink_structure, $tokens);

		$num_tokens = count($tokens[0]);

		$index = $this->index; //probably 'index.php'
		$feedindex = $index;
		$trackbackindex = $index;
		$embedindex = $index;

		/*
		 * Build a list from the rewritecode and queryreplace arrays, that will look something
		 * like tagname=$matches[i] where i is the current $i.
		 */
		$queries = array();
		for ( $i = 0; $i < $num_tokens; ++$i ) {
			if ( 0 < $i )
				$queries[$i] = $queries[$i - 1] . '&';
			else
				$queries[$i] = '';

                    /** rewrite rule中支持token模板变量, 这里要把模板变量替换成正则变达式?
                    将rewrite rule中的模板变量%xxx%全部消掉，替换成正常的
                    %xxx%与正则变达式的关系是由add_rewrite_tag()注册时加入的
                    */
			$query_token = str_replace($this->rewritecode, $this->queryreplace, $tokens[0][$i]) . $this->preg_index($i+1);
			$queries[$i] .= $query_token;
		}

		// Get the structure, minus any cruft (stuff that isn't tags) at the front.
		$structure = $permalink_structure;
		if ( $front != '/' )
			$structure = str_replace($front, '', $structure);

		/*
		 * Create a list of dirs to walk over, making rewrite rules for each level
		 * so for example, a $structure of /%year%/%monthnum%/%postname% would create
		 * rewrite rules for /%year%/, /%year%/%monthnum%/ and /%year%/%monthnum%/%postname%
		 */
		 /** 逐个token进行处理? */
		$structure = trim($structure, '/');
		$dirs = $walk_dirs ? explode('/', $structure) : array( $structure );
		$num_dirs = count($dirs);

		// Strip slashes from the front of $front.
		$front = preg_replace('|^/+|', '', $front);

		// The main workhorse loop.
		$post_rewrite = array();
		$struct = $front;
		for ( $j = 0; $j < $num_dirs; ++$j ) {
			// Get the struct for this dir, and trim slashes off the front.
			$struct .= $dirs[$j] . '/'; // Accumulate. see comment near explode('/', $structure) above.
			$struct = ltrim($struct, '/');

			// Replace tags with regexes.
			$match = str_replace($this->rewritecode, $this->rewritereplace, $struct);

			// Make a list of tags, and store how many there are in $num_toks.
			$num_toks = preg_match_all('/%.+?%/', $struct, $toks);

			// Get the 'tagname=$matches[i]'.
			$query = ( ! empty( $num_toks ) && isset( $queries[$num_toks - 1] ) ) ? $queries[$num_toks - 1] : '';

			// Set up $ep_mask_specific which is used to match more specific URL types.
			switch ( $dirs[$j] ) {
				case '%year%':
					$ep_mask_specific = EP_YEAR;
					break;
				case '%monthnum%':
					$ep_mask_specific = EP_MONTH;
					break;
				case '%day%':
					$ep_mask_specific = EP_DAY;
					break;
				default:
					$ep_mask_specific = EP_NONE;
			}

			// Create query for /page/xx.
			$pagematch = $match . $pageregex;
			$pagequery = $index . '?' . $query . '&paged=' . $this->preg_index($num_toks + 1);

			// Create query for /comment-page-xx.
			$commentmatch = $match . $commentregex;
			$commentquery = $index . '?' . $query . '&cpage=' . $this->preg_index($num_toks + 1);

			if ( get_option('page_on_front') ) {
				// Create query for Root /comment-page-xx.
				$rootcommentmatch = $match . $commentregex;
				$rootcommentquery = $index . '?' . $query . '&page_id=' . get_option('page_on_front') . '&cpage=' . $this->preg_index($num_toks + 1);
			}

			// Create query for /feed/(feed|atom|rss|rss2|rdf).
			$feedmatch = $match . $feedregex;
			$feedquery = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			// Create query for /(feed|atom|rss|rss2|rdf) (see comment near creation of $feedregex).
			$feedmatch2 = $match . $feedregex2;
			$feedquery2 = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			// Create query and regex for embeds.
			$embedmatch = $match . $embedregex;
			$embedquery = $embedindex . '?' . $query . '&embed=true';

			// If asked to, turn the feed queries into comment feed ones.
			if ( $forcomments ) {
				$feedquery .= '&withcomments=1';
				$feedquery2 .= '&withcomments=1';
			}

			// Start creating the array of rewrites for this dir.
			$rewrite = array();

			// ...adding on /feed/ regexes => queries
			if ( $feed ) {
				$rewrite = array( $feedmatch => $feedquery, $feedmatch2 => $feedquery2, $embedmatch => $embedquery );
			}

			//...and /page/xx ones
			if ( $paged ) {
				$rewrite = array_merge( $rewrite, array( $pagematch => $pagequery ) );
			}

			// Only on pages with comments add ../comment-page-xx/.
			if ( EP_PAGES & $ep_mask || EP_PERMALINK & $ep_mask ) {
				$rewrite = array_merge($rewrite, array($commentmatch => $commentquery));
			} elseif ( EP_ROOT & $ep_mask && get_option('page_on_front') ) {
				$rewrite = array_merge($rewrite, array($rootcommentmatch => $rootcommentquery));
			}

			// Do endpoints.
			if ( $endpoints ) {
				foreach ( (array) $ep_query_append as $regex => $ep) {
					// Add the endpoints on if the mask fits.
					if ( $ep[0] & $ep_mask || $ep[0] & $ep_mask_specific )
						$rewrite[$match . $regex] = $index . '?' . $query . $ep[1] . $this->preg_index($num_toks + 2);
				}
			}

			// If we've got some tags in this dir.
			if ( $num_toks ) {
				$post = false;
				$page = false;

				/*
				 * Check to see if this dir is permalink-level: i.e. the structure specifies an
				 * individual post. Do this by checking it contains at least one of 1) post name,
				 * 2) post ID, 3) page name, 4) timestamp (year, month, day, hour, second and
				 * minute all present). Set these flags now as we need them for the endpoints.
				 */
				if ( strpos($struct, '%postname%') !== false
						|| strpos($struct, '%post_id%') !== false
						|| strpos($struct, '%pagename%') !== false
						|| (strpos($struct, '%year%') !== false && strpos($struct, '%monthnum%') !== false && strpos($struct, '%day%') !== false && strpos($struct, '%hour%') !== false && strpos($struct, '%minute%') !== false && strpos($struct, '%second%') !== false)
						) {
					$post = true;
					if ( strpos($struct, '%pagename%') !== false )
						$page = true;
				}

				if ( ! $post ) {
					// For custom post types, we need to add on endpoints as well.
					foreach ( get_post_types( array('_builtin' => false ) ) as $ptype ) {
						if ( strpos($struct, "%$ptype%") !== false ) {
							$post = true;

							// This is for page style attachment URLs.
							$page = is_post_type_hierarchical( $ptype );
							break;
						}
					}
				}

				// If creating rules for a permalink, do all the endpoints like attachments etc.
				if ( $post ) {
					// Create query and regex for trackback.
					$trackbackmatch = $match . $trackbackregex;
					$trackbackquery = $trackbackindex . '?' . $query . '&tb=1';

					// Create query and regex for embeds.
					$embedmatch = $match . $embedregex;
					$embedquery = $embedindex . '?' . $query . '&embed=true';

					// Trim slashes from the end of the regex for this dir.
					$match = rtrim($match, '/');

					// Get rid of brackets.
					$submatchbase = str_replace( array('(', ')'), '', $match);

					// Add a rule for at attachments, which take the form of <permalink>/some-text.
					$sub1 = $submatchbase . '/([^/]+)/';

					// Add trackback regex <permalink>/trackback/...
					$sub1tb = $sub1 . $trackbackregex;

					// And <permalink>/feed/(atom|...)
					$sub1feed = $sub1 . $feedregex;

					// And <permalink>/(feed|atom...)
					$sub1feed2 = $sub1 . $feedregex2;

					// And <permalink>/comment-page-xx
					$sub1comment = $sub1 . $commentregex;

					// And <permalink>/embed/...
					$sub1embed = $sub1 . $embedregex;

					/*
					 * Add another rule to match attachments in the explicit form:
					 * <permalink>/attachment/some-text
					 */
					$sub2 = $submatchbase . '/attachment/([^/]+)/';

					// And add trackbacks <permalink>/attachment/trackback.
					$sub2tb = $sub2 . $trackbackregex;

					// Feeds, <permalink>/attachment/feed/(atom|...)
					$sub2feed = $sub2 . $feedregex;

					// And feeds again on to this <permalink>/attachment/(feed|atom...)
					$sub2feed2 = $sub2 . $feedregex2;

					// And <permalink>/comment-page-xx
					$sub2comment = $sub2 . $commentregex;

					// And <permalink>/embed/...
					$sub2embed = $sub2 . $embedregex;

					// Create queries for these extra tag-ons we've just dealt with.
					$subquery = $index . '?attachment=' . $this->preg_index(1);
					$subtbquery = $subquery . '&tb=1';
					$subfeedquery = $subquery . '&feed=' . $this->preg_index(2);
					$subcommentquery = $subquery . '&cpage=' . $this->preg_index(2);
					$subembedquery = $subquery . '&embed=true';

					// Do endpoints for attachments.
					if ( !empty($endpoints) ) {
						foreach ( (array) $ep_query_append as $regex => $ep ) {
							if ( $ep[0] & EP_ATTACHMENT ) {
								$rewrite[$sub1 . $regex] = $subquery . $ep[1] . $this->preg_index(3);
								$rewrite[$sub2 . $regex] = $subquery . $ep[1] . $this->preg_index(3);
							}
						}
					}

					/*
					 * Now we've finished with endpoints, finish off the $sub1 and $sub2 matches
					 * add a ? as we don't have to match that last slash, and finally a $ so we
					 * match to the end of the URL
					 */
					$sub1 .= '?$';
					$sub2 .= '?$';

					/*
					 * Post pagination, e.g. <permalink>/2/
					 * Previously: '(/[0-9]+)?/?$', which produced '/2' for page.
					 * When cast to int, returned 0.
					 */
					$match = $match . '(?:/([0-9]+))?/?$';
					$query = $index . '?' . $query . '&page=' . $this->preg_index($num_toks + 1);

				// Not matching a permalink so this is a lot simpler.
				} else {
					// Close the match and finalise the query.
					$match .= '?$';
					$query = $index . '?' . $query;
				}

				/*
				 * Create the final array for this dir by joining the $rewrite array (which currently
				 * only contains rules/queries for trackback, pages etc) to the main regex/query for
				 * this dir
				 */
				$rewrite = array_merge($rewrite, array($match => $query));

				// If we're matching a permalink, add those extras (attachments etc) on.
				if ( $post ) {
					// Add trackback.
					$rewrite = array_merge(array($trackbackmatch => $trackbackquery), $rewrite);

					// Add embed.
					$rewrite = array_merge( array( $embedmatch => $embedquery ), $rewrite );

					// Add regexes/queries for attachments, attachment trackbacks and so on.
					if ( ! $page ) {
						// Require <permalink>/attachment/stuff form for pages because of confusion with subpages.
						$rewrite = array_merge( $rewrite, array(
							$sub1        => $subquery,
							$sub1tb      => $subtbquery,
							$sub1feed    => $subfeedquery,
							$sub1feed2   => $subfeedquery,
							$sub1comment => $subcommentquery,
							$sub1embed   => $subembedquery
						) );
					}

					$rewrite = array_merge( array( $sub2 => $subquery, $sub2tb => $subtbquery, $sub2feed => $subfeedquery, $sub2feed2 => $subfeedquery, $sub2comment => $subcommentquery, $sub2embed => $subembedquery ), $rewrite );
				}
			}
			// Add the rules for this dir to the accumulating $post_rewrite.
			$post_rewrite = array_merge($rewrite, $post_rewrite);
		}

		// The finished rules. phew!
		return $post_rewrite;
	}

	/**
	 * Generates rewrite rules with permalink structure and walking directory only.
	 *
	 * Shorten version of WP_Rewrite::generate_rewrite_rules() that allows for shorter
	 * list of parameters. See the method for longer description of what generating
	 * rewrite rules does.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @see WP_Rewrite::generate_rewrite_rules() See for long description and rest of parameters.
	 *
	 * @param string $permalink_structure The permalink structure to generate rules.
	 * @param bool   $walk_dirs           Optional, default is false. Whether to create list of directories to walk over.
	 * @return array
	 */
	public function generate_rewrite_rule($permalink_structure, $walk_dirs = false) {
		return $this->generate_rewrite_rules($permalink_structure, EP_NONE, false, false, false, $walk_dirs);
	}

	/**
	 * Constructs rewrite matches and queries from permalink structure.
	 *
	 * Runs the action 'generate_rewrite_rules' with the parameter that is an
	 * reference to the current WP_Rewrite instance to further manipulate the
	 * permalink structures and rewrite rules. Runs the 'rewrite_rules_array'
	 * filter on the full rewrite rule array.
	 *
	 * There are two ways to manipulate the rewrite rules, one by hooking into
	 * the 'generate_rewrite_rules' action and gaining full control of the
	 * object or just manipulating the rewrite rule array before it is passed
	 * from the function.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return array An associate array of matches and queries.
	 */
	 /***
	 根据db中的permalink_structure和内存变量wp->extra_permastructs(存放category匹配串的)，生成一组rewrite rules
	 对category, tag这种taxomony的rewrite rule 起源于register_taxonomy(), create_initial_taxonomies()中的定义
	 */
	public function rewrite_rules() {
		$rewrite = array();

		if ( empty($this->permalink_structure) )
			return $rewrite;

		// robots.txt -only if installed at the root
		$home_path = parse_url( home_url() );
		$robots_rewrite = ( empty( $home_path['path'] ) || '/' == $home_path['path'] ) ? array( 'robots\.txt$' => $this->index . '?robots=1' ) : array();

		// Old feed and service files.
		$deprecated_files = array(
			'.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => $this->index . '?feed=old',
			'.*wp-app\.php(/.*)?$' => $this->index . '?error=403',
		);

		// Registration rules.
		$registration_pages = array();
		if ( is_multisite() && is_main_site() ) {
			$registration_pages['.*wp-signup.php$'] = $this->index . '?signup=true';
			$registration_pages['.*wp-activate.php$'] = $this->index . '?activate=true';
		}

		// Deprecated.
		$registration_pages['.*wp-register.php$'] = $this->index . '?register=true';

		// Post rewrite rules.
		/*** 以下有生成post, page, date, comments_rewrite,... , 规则 的，category的rule在哪里生成呢? 
		*/
		$post_rewrite = $this->generate_rewrite_rules( $this->permalink_structure, EP_PERMALINK );

		/**
		 * Filter rewrite rules used for "post" archives.
		 *
		 * @since 1.5.0
		 *
		 * @param array $post_rewrite The rewrite rules for posts.
		 */
		$post_rewrite = apply_filters( 'post_rewrite_rules', $post_rewrite );

		// Date rewrite rules.
		$date_rewrite = $this->generate_rewrite_rules($this->get_date_permastruct(), EP_DATE);

		/**
		 * Filter rewrite rules used for date archives.
		 *
		 * Likely date archives would include /yyyy/, /yyyy/mm/, and /yyyy/mm/dd/.
		 *
		 * @since 1.5.0
		 *
		 * @param array $date_rewrite The rewrite rules for date archives.
		 */
		$date_rewrite = apply_filters( 'date_rewrite_rules', $date_rewrite );

		// Root-level rewrite rules.
		$root_rewrite = $this->generate_rewrite_rules($this->root . '/', EP_ROOT);

		/**
		 * Filter rewrite rules used for root-level archives.
		 *
		 * Likely root-level archives would include pagination rules for the homepage
		 * as well as site-wide post feeds (e.g. /feed/, and /feed/atom/).
		 *
		 * @since 1.5.0
		 *
		 * @param array $root_rewrite The root-level rewrite rules.
		 */
		$root_rewrite = apply_filters( 'root_rewrite_rules', $root_rewrite );

		// Comments rewrite rules.
		$comments_rewrite = $this->generate_rewrite_rules($this->root . $this->comments_base, EP_COMMENTS, false, true, true, false);

		/**
		 * Filter rewrite rules used for comment feed archives.
		 *
		 * Likely comments feed archives include /comments/feed/, and /comments/feed/atom/.
		 *
		 * @since 1.5.0
		 *
		 * @param array $comments_rewrite The rewrite rules for the site-wide comments feeds.
		 */
		$comments_rewrite = apply_filters( 'comments_rewrite_rules', $comments_rewrite );

		// Search rewrite rules.
		$search_structure = $this->get_search_permastruct();
		$search_rewrite = $this->generate_rewrite_rules($search_structure, EP_SEARCH);

		/**
		 * Filter rewrite rules used for search archives.
		 *
		 * Likely search-related archives include /search/search+query/ as well as
		 * pagination and feed paths for a search.
		 *
		 * @since 1.5.0
		 *
		 * @param array $search_rewrite The rewrite rules for search queries.
		 */
		$search_rewrite = apply_filters( 'search_rewrite_rules', $search_rewrite );

		// Author rewrite rules.
		$author_rewrite = $this->generate_rewrite_rules($this->get_author_permastruct(), EP_AUTHORS);

		/**
		 * Filter rewrite rules used for author archives.
		 *
		 * Likely author archives would include /author/author-name/, as well as
		 * pagination and feed paths for author archives.
		 *
		 * @since 1.5.0
		 *
		 * @param array $author_rewrite The rewrite rules for author archives.
		 */
		$author_rewrite = apply_filters( 'author_rewrite_rules', $author_rewrite );

		// Pages rewrite rules.
		 /** 产生页面rewrite rule*/		
		$page_rewrite = $this->page_rewrite_rules();

		/**
		 * Filter rewrite rules used for "page" post type archives.
		 *
		 * @since 1.5.0
		 *
		 * @param array $page_rewrite The rewrite rules for the "page" post type.
		 */
		$page_rewrite = apply_filters( 'page_rewrite_rules', $page_rewrite );

		// Extra permastructs.
		/** 
		category的rule在这里生成呢! 
		Extra permastructs表示内存中的固定链接, 像category这种的固定链接并不是放在db中，而是放在全局变量中,
		即在register_taxonomy()中add_permastruct()时会准备好$this->extra_permastructs 
		*/
		foreach ( $this->extra_permastructs as $permastructname => $struct ) {
			if ( is_array( $struct ) ) {
				if ( count( $struct ) == 2 )
					$rules = $this->generate_rewrite_rules( $struct[0], $struct[1] );
				else
					$rules = $this->generate_rewrite_rules( $struct['struct'], $struct['ep_mask'], $struct['paged'], $struct['feed'], $struct['forcomments'], $struct['walk_dirs'], $struct['endpoints'] );
			} else {
				$rules = $this->generate_rewrite_rules( $struct );
			}

			/**
			 * Filter rewrite rules used for individual permastructs.
			 *
			 * The dynamic portion of the hook name, `$permastructname`, refers
			 * to the name of the registered permastruct, e.g. 'post_tag' (tags),
			 * 'category' (categories), etc.
			 *
			 * @since 3.1.0
			 *
			 * @param array $rules The rewrite rules generated for the current permastruct.
			 */
			$rules = apply_filters( $permastructname . '_rewrite_rules', $rules );
			if ( 'post_tag' == $permastructname ) {

				/**
				 * Filter rewrite rules used specifically for Tags.
				 *
				 * @since 2.3.0
				 * @deprecated 3.1.0 Use 'post_tag_rewrite_rules' instead
				 *
				 * @param array $rules The rewrite rules generated for tags.
				 */
				$rules = apply_filters( 'tag_rewrite_rules', $rules );
			}

			$this->extra_rules_top = array_merge($this->extra_rules_top, $rules);
		}

		// Put them together.
		if ( $this->use_verbose_page_rules )
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $deprecated_files, $registration_pages, $root_rewrite, $comments_rewrite, $search_rewrite,  $author_rewrite, $date_rewrite, $page_rewrite, $post_rewrite, $this->extra_rules);
		else
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $deprecated_files, $registration_pages, $root_rewrite, $comments_rewrite, $search_rewrite,  $author_rewrite, $date_rewrite, $post_rewrite, $page_rewrite, $this->extra_rules);

		/**
		 * Fires after the rewrite rules are generated.
		 *
		 * @since 1.5.0
		 *
		 * @param WP_Rewrite $this Current WP_Rewrite instance, passed by reference.
		 */
		do_action_ref_array( 'generate_rewrite_rules', array( &$this ) );

		/**
		 * Filter the full set of generated rewrite rules.
		 *
		 * @since 1.5.0
		 *
		 * @param array $this->rules The compiled array of rewrite rules.
		 */
		$this->rules = apply_filters( 'rewrite_rules_array', $this->rules );

		return $this->rules;
	}

	/**
	 * Retrieves the rewrite rules.
	 *
	 * The difference between this method and WP_Rewrite::rewrite_rules() is that
	 * this method stores the rewrite rules in the 'rewrite_rules' option and retrieves
	 * it. This prevents having to process all of the permalinks to get the rewrite rules
	 * in the form of caching.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return array Rewrite rules.
	 */
	 /*** 读取db中的rewrite规则 */
	public function wp_rewrite_rules() {
		$this->rules = get_option('rewrite_rules'); 
            /**
            $this->rules
            [
                ^wp-json/?$ = "index.php?rest_route=/"
                ^wp-json/(.*)? = "index.php?rest_route=/$matches[1]"
                ^index.php/wp-json/?$ = "index.php?rest_route=/"
                ^index.php/wp-json/(.*)? = "index.php?rest_route=/$matches[1]"
                
                category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?category_name=$matches[1]&feed=$matches[2]"
                category/(.+?)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?category_name=$matches[1]&feed=$matches[2]"
                category/(.+?)/embed/?$ = "index.php?category_name=$matches[1]&embed=true"
                category/(.+?)/page/?([0-9]{1,})/?$ = "index.php?category_name=$matches[1]&paged=$matches[2]"   // 比如url中是page=9,但解析后query_vars['paged']=9中
                // 这里可以看出category/xxx 实际上是取slug为category_name的, 这个是怎么来源的?
                // 对category, tag这种taxomony的rewrite则是来源于register_taxonomy(), create_initial_taxonomies()中的定义
                category/(.+?)/?$ = "index.php?category_name=$matches[1]" 
                
                tag/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?tag=$matches[1]&feed=$matches[2]"
                tag/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?tag=$matches[1]&feed=$matches[2]"
                tag/([^/]+)/embed/?$ = "index.php?tag=$matches[1]&embed=true"
                tag/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?tag=$matches[1]&paged=$matches[2]"
                tag/([^/]+)/?$ = "index.php?tag=$matches[1]"
                type/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?post_format=$matches[1]&feed=$matches[2]"
                type/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?post_format=$matches[1]&feed=$matches[2]"
                type/([^/]+)/embed/?$ = "index.php?post_format=$matches[1]&embed=true"
                type/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?post_format=$matches[1]&paged=$matches[2]"
                type/([^/]+)/?$ = "index.php?post_format=$matches[1]"
                .*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$ = "index.php?feed=old"
                .*wp-app\.php(/.*)?$ = "index.php?error=403"
                .*wp-register.php$ = "index.php?register=true"
                feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]"
                (feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]"
                embed/?$ = "index.php?&embed=true"
                page/?([0-9]{1,})/?$ = "index.php?&paged=$matches[1]"
                comments/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]&withcomments=1"
                comments/(feed|rdf|rss|rss2|atom)/?$ = "index.php?&feed=$matches[1]&withcomments=1"
                comments/embed/?$ = "index.php?&embed=true"
                search/(.+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?s=$matches[1]&feed=$matches[2]"
                search/(.+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?s=$matches[1]&feed=$matches[2]"
                search/(.+)/embed/?$ = "index.php?s=$matches[1]&embed=true"
                search/(.+)/page/?([0-9]{1,})/?$ = "index.php?s=$matches[1]&paged=$matches[2]"
                search/(.+)/?$ = "index.php?s=$matches[1]"
                author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?author_name=$matches[1]&feed=$matches[2]"
                author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?author_name=$matches[1]&feed=$matches[2]"
                author/([^/]+)/embed/?$ = "index.php?author_name=$matches[1]&embed=true"
                author/([^/]+)/page/?([0-9]{1,})/?$ = "index.php?author_name=$matches[1]&paged=$matches[2]"
                author/([^/]+)/?$ = "index.php?author_name=$matches[1]"
                ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]"
                ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]"
                ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&embed=true"
                ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]"
                ([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]"
                ([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]"
                ([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]"
                ([0-9]{4})/([0-9]{1,2})/embed/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&embed=true"
                ([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]"
                ([0-9]{4})/([0-9]{1,2})/?$ = "index.php?year=$matches[1]&monthnum=$matches[2]"
                ([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&feed=$matches[2]"
                ([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$ = "index.php?year=$matches[1]&feed=$matches[2]"
                ([0-9]{4})/embed/?$ = "index.php?year=$matches[1]&embed=true"
                ([0-9]{4})/page/?([0-9]{1,})/?$ = "index.php?year=$matches[1]&paged=$matches[2]"
                ([0-9]{4})/?$ = "index.php?year=$matches[1]"
                .?.+?/attachment/([^/]+)/?$ = "index.php?attachment=$matches[1]"
                .?.+?/attachment/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
                .?.+?/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                .?.+?/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                .?.+?/attachment/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
                .?.+?/attachment/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
                (.?.+?)/embed/?$ = "index.php?pagename=$matches[1]&embed=true"
                (.?.+?)/trackback/?$ = "index.php?pagename=$matches[1]&tb=1"
                (.?.+?)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?pagename=$matches[1]&feed=$matches[2]"
                (.?.+?)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?pagename=$matches[1]&feed=$matches[2]"
                (.?.+?)/page/?([0-9]{1,})/?$ = "index.php?pagename=$matches[1]&paged=$matches[2]"
                (.?.+?)/comment-page-([0-9]{1,})/?$ = "index.php?pagename=$matches[1]&cpage=$matches[2]"
                (.?.+?)(?:/([0-9]+))?/?$ = "index.php?pagename=$matches[1]&page=$matches[2]"
                [^/]+/attachment/([^/]+)/?$ = "index.php?attachment=$matches[1]"
                [^/]+/attachment/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
                [^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                [^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                [^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
                [^/]+/attachment/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
                ([^/]+)/embed/?$ = "index.php?name=$matches[1]&embed=true"
                ([^/]+)/trackback/?$ = "index.php?name=$matches[1]&tb=1"
                ([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?name=$matches[1]&feed=$matches[2]"
                ([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?name=$matches[1]&feed=$matches[2]"
                ([^/]+)/page/?([0-9]{1,})/?$ = "index.php?name=$matches[1]&paged=$matches[2]"
                ([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?name=$matches[1]&cpage=$matches[2]"
                ([^/]+)(?:/([0-9]+))?/?$ = "index.php?name=$matches[1]&page=$matches[2]"
                [^/]+/([^/]+)/?$ = "index.php?attachment=$matches[1]"
                [^/]+/([^/]+)/trackback/?$ = "index.php?attachment=$matches[1]&tb=1"
                [^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                [^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$ = "index.php?attachment=$matches[1]&feed=$matches[2]"
                [^/]+/([^/]+)/comment-page-([0-9]{1,})/?$ = "index.php?attachment=$matches[1]&cpage=$matches[2]"
                [^/]+/([^/]+)/embed/?$ = "index.php?attachment=$matches[1]&embed=true"
            ]
            */		
		if ( empty($this->rules) ) {
			$this->matches = 'matches';
			/*** 
			根据db中的permalink-structure和内存变量$wp->extra_permastructs(存放category的)，生成rules, 再存到db中
                    在后台界面设置固定链接(permalink), 选朴素时db中的值为空, 
                    选其它时db中的值为/%postname%/等时, 表示打开美化开关
			*/
			$this->rewrite_rules();
			update_option('rewrite_rules', $this->rules);
		}

		return $this->rules;
	}

	/**
	 * Retrieves mod_rewrite-formatted rewrite rules to write to .htaccess.
	 *
	 * Does not actually write to the .htaccess file, but creates the rules for
	 * the process that will.
	 *
	 * Will add the non_wp_rules property rules to the .htaccess file before
	 * the WordPress rewrite rules one.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @return string
	 */
	 /*** 生成.htaccess中用到的规则, for apache */
	public function mod_rewrite_rules() {
		if ( ! $this->using_permalinks() )
			return '';

		$site_root = parse_url( site_url() );
		if ( isset( $site_root['path'] ) )
			$site_root = trailingslashit($site_root['path']);

		$home_root = parse_url(home_url());
		if ( isset( $home_root['path'] ) )
			$home_root = trailingslashit($home_root['path']);
		else
			$home_root = '/';

		$rules = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteBase $home_root\n";

		// Prevent -f checks on index.php.
		$rules .= "RewriteRule ^index\.php$ - [L]\n";

		// Add in the rules that don't redirect to WP's index.php (and thus shouldn't be handled by WP at all).
		foreach ( (array) $this->non_wp_rules as $match => $query) {
			// Apache 1.3 does not support the reluctant (non-greedy) modifier.
			$match = str_replace('.+?', '.+', $match);

			$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
		}

		if ( $this->use_verbose_rules ) {
			$this->matches = '';
			$rewrite = $this->rewrite_rules();
			$num_rules = count($rewrite);
			$rules .= "RewriteCond %{REQUEST_FILENAME} -f [OR]\n" .
				"RewriteCond %{REQUEST_FILENAME} -d\n" .
				"RewriteRule ^.*$ - [S=$num_rules]\n";

			foreach ( (array) $rewrite as $match => $query) {
				// Apache 1.3 does not support the reluctant (non-greedy) modifier.
				$match = str_replace('.+?', '.+', $match);

				if ( strpos($query, $this->index) !== false )
					$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
				else
					$rules .= 'RewriteRule ^' . $match . ' ' . $site_root . $query . " [QSA,L]\n";
			}
		} else {
			$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n" .
				"RewriteCond %{REQUEST_FILENAME} !-d\n" .
				"RewriteRule . {$home_root}{$this->index} [L]\n";
		}

		$rules .= "</IfModule>\n";

		/**
		 * Filter the list of rewrite rules formatted for output to an .htaccess file.
		 *
		 * @since 1.5.0
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		$rules = apply_filters( 'mod_rewrite_rules', $rules );

		/**
		 * Filter the list of rewrite rules formatted for output to an .htaccess file.
		 *
		 * @since 1.5.0
		 * @deprecated 1.5.0 Use the mod_rewrite_rules filter instead.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		return apply_filters( 'rewrite_rules', $rules );
	}

	/**
	 * Retrieves IIS7 URL Rewrite formatted rewrite rules to write to web.config file.
	 *
	 * Does not actually write to the web.config file, but creates the rules for
	 * the process that will.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param bool $add_parent_tags Optional. Whether to add parent tags to the rewrite rule sets.
	 *                              Default false.
	 * @return string IIS7 URL rewrite rule sets.
	 */
	public function iis7_url_rewrite_rules( $add_parent_tags = false ) {
		if ( ! $this->using_permalinks() )
			return '';
		$rules = '';
		if ( $add_parent_tags ) {
			$rules .= '<configuration>
	<system.webServer>
		<rewrite>
			<rules>';
		}

		$rules .= '
			<rule name="WordPress: ' . esc_attr( home_url() ) . '" patternSyntax="Wildcard">
				<match url="*" />
					<conditions>
						<add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
						<add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
					</conditions>
				<action type="Rewrite" url="index.php" />
			</rule>';

		if ( $add_parent_tags ) {
			$rules .= '
			</rules>
		</rewrite>
	</system.webServer>
</configuration>';
		}

		/**
		 * Filter the list of rewrite rules formatted for output to a web.config.
		 *
		 * @since 2.8.0
		 *
		 * @param string $rules Rewrite rules formatted for IIS web.config.
		 */
		return apply_filters( 'iis7_url_rewrite_rules', $rules );
	}

	/**
	 * Adds a rewrite rule that transforms a URL structure to a set of query vars.
	 *
	 * Any value in the $after parameter that isn't 'bottom' will result in the rule
	 * being placed at the top of the rewrite rules.
	 *
	 * @since 2.1.0
	 * @since 4.4.0 Array support was added to the `$query` parameter.
	 * @access public
	 *
	 * @param string       $regex Regular expression to match request against.
	 * @param string|array $query The corresponding query vars for this rewrite rule.
	 * @param string       $after Optional. Priority of the new rule. Accepts 'top'
	 *                            or 'bottom'. Default 'bottom'.
	 */
	public function add_rule( $regex, $query, $after = 'bottom' ) {
		if ( is_array( $query ) ) {
			$external = false;
			$query = add_query_arg( $query, 'index.php' );
		} else {
			$index = false === strpos( $query, '?' ) ? strlen( $query ) : strpos( $query, '?' );
			$front = substr( $query, 0, $index );

			$external = $front != $this->index;
		}

		// "external" = it doesn't correspond to index.php.
		if ( $external ) {
			$this->add_external_rule( $regex, $query );
		} else {
			if ( 'bottom' == $after ) {
				$this->extra_rules = array_merge( $this->extra_rules, array( $regex => $query ) );
			} else {
				$this->extra_rules_top = array_merge( $this->extra_rules_top, array( $regex => $query ) );
			}
		}
	}

	/**
	 * Adds a rewrite rule that doesn't correspond to index.php.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @param string $regex Regular expression to match request against.
	 * @param string $query The corresponding query vars for this rewrite rule.
	 */
	public function add_external_rule( $regex, $query ) {
		$this->non_wp_rules[ $regex ] = $query;
	}

	/**
	 * Adds an endpoint, like /trackback/.
	 *
	 * @since 2.1.0
	 * @since 3.9.0 $query_var parameter added.
	 * @since 4.3.0 Added support for skipping query var registration by passing `false` to `$query_var`.
	 * @access public
	 *
	 * @see add_rewrite_endpoint() for full documentation.
	 * @global WP $wp
	 *
	 * @param string      $name      Name of the endpoint.
	 * @param int         $places    Endpoint mask describing the places the endpoint should be added.
	 * @param string|bool $query_var Optional. Name of the corresponding query variable. Pass `false` to
	 *                               skip registering a query_var for this endpoint. Defaults to the
	 *                               value of `$name`.
	 */
	public function add_endpoint( $name, $places, $query_var = true ) {
		global $wp;

		// For backward compatibility, if null has explicitly been passed as `$query_var`, assume `true`.
		if ( true === $query_var || null === func_get_arg( 2 ) ) {
			$query_var = $name;
		}
		$this->endpoints[] = array( $places, $name, $query_var );

		if ( $query_var ) {
			$wp->add_query_var( $query_var );
		}
	}

	/**
	 * Adds a new permalink structure.
	 *
	 * A permalink structure (permastruct) is an abstract definition of a set of rewrite rules;
	 * it is an easy way of expressing a set of regular expressions that rewrite to a set of
	 * query strings. The new permastruct is added to the WP_Rewrite::$extra_permastructs array.
	 *
	 * When the rewrite rules are built by WP_Rewrite::rewrite_rules(), all of these extra
	 * permastructs are passed to WP_Rewrite::generate_rewrite_rules() which transforms them
	 * into the regular expressions that many love to hate.
	 *
	 * The `$args` parameter gives you control over how WP_Rewrite::generate_rewrite_rules()
	 * works on the new permastruct.
	 *
	 * @since 2.5.0
	 * @access public
	 *
	 * @param string $name   Name for permalink structure.
	 * @param string $struct Permalink structure (e.g. category/%category%)
	 * @param array  $args   {
	 *     Optional. Arguments for building rewrite rules based on the permalink structure.
	 *     Default empty array.
	 *
	 *     @type bool $with_front  Whether the structure should be prepended with `WP_Rewrite::$front`.
	 *                             Default true.
	 *     @type int  $ep_mask     The endpoint mask defining which endpoints are added to the structure.
	 *                             Accepts `EP_NONE`, `EP_PERMALINK`, `EP_ATTACHMENT`, `EP_DATE`, `EP_YEAR`,
	 *                             `EP_MONTH`, `EP_DAY`, `EP_ROOT`, `EP_COMMENTS`, `EP_SEARCH`, `EP_CATEGORIES`,
	 *                             `EP_TAGS`, `EP_AUTHORS`, `EP_PAGES`, `EP_ALL_ARCHIVES`, and `EP_ALL`.
	 *                             Default `EP_NONE`.
	 *     @type bool $paged       Whether archive pagination rules should be added for the structure.
	 *                             Default true.
	 *     @type bool $feed        Whether feed rewrite rules should be added for the structure. Default true.
	 *     @type bool $forcomments Whether the feed rules should be a query for a comments feed. Default false.
	 *     @type bool $walk_dirs   Whether the 'directories' making up the structure should be walked over
	 *                             and rewrite rules built for each in-turn. Default true.
	 *     @type bool $endpoints   Whether endpoints should be applied to the generated rules. Default true.
	 * }
	 */
	 /*** 扩展固定链接, 何用? 
	 用于存放category这种的permalinks
	 extra_permastructs + db中的permalinks --> rewrite rules
	 既可以通过add_permastruct(), 也可以通过管理界面操作db中的permalinks, 还可以直接add_rewrite_rule()，它们的目的都是增加rewrite rules
	 */
	public function add_permastruct( $name, $struct, $args = array() ) {
		// Backwards compatibility for the old parameters: $with_front and $ep_mask.
		if ( ! is_array( $args ) )
			$args = array( 'with_front' => $args );
		if ( func_num_args() == 4 )
			$args['ep_mask'] = func_get_arg( 3 );

		$defaults = array(
			'with_front' => true,
			'ep_mask' => EP_NONE,
			'paged' => true,
			'feed' => true,
			'forcomments' => false,
			'walk_dirs' => true,
			'endpoints' => true,
		);
		$args = array_intersect_key( $args, $defaults );
		$args = wp_parse_args( $args, $defaults );

		if ( $args['with_front'] )
			$struct = $this->front . $struct;
		else
			$struct = $this->root . $struct;
		$args['struct'] = $struct;

            /*** 
            对于post之类的固定链接是保存在db中，
            对于像category的taxonomy的固定链接是通过注册保存在内存 $this->extra_permastructs[ $name ]中*/
	    $this->extra_permastructs[ $name ] = $args;
	}

	/**
	 * Removes a permalink structure.
	 *
	 * @since 4.5.0
	 * @access public
	 *
	 * @param string $name Name for permalink structure.
	 */
	public function remove_permastruct( $name ) {
		unset( $this->extra_permastructs[ $name ] );
	}

	/**
	 * Removes rewrite rules and then recreate rewrite rules.
	 *
	 * Calls WP_Rewrite::wp_rewrite_rules() after removing the 'rewrite_rules' option.
	 * If the function named 'save_mod_rewrite_rules' exists, it will be called.
	 *
	 * @since 2.0.1
	 * @access public
	 *
	 * @staticvar bool $do_hard_later
	 *
	 * @param bool $hard Whether to update .htaccess (hard flush) or just update rewrite_rules option (soft flush). Default is true (hard).
	 */
	 /** 
	 清掉db中的rewrite_rules, 根据db中的permalink重新生成一次
	 如果是apache, .htacess可写的话，重新生成.htacess
	 */
	public function flush_rules( $hard = true ) {
		static $do_hard_later = null;

		// Prevent this action from running before everyone has registered their rewrites.
		if ( ! did_action( 'wp_loaded' ) ) {
			add_action( 'wp_loaded', array( $this, 'flush_rules' ) );
			$do_hard_later = ( isset( $do_hard_later ) ) ? $do_hard_later || $hard : $hard;
			return;
		}

		if ( isset( $do_hard_later ) ) {
			$hard = $do_hard_later;
			unset( $do_hard_later );
		}

		update_option( 'rewrite_rules', '' );
		$this->wp_rewrite_rules();

		/**
		 * Filter whether a "hard" rewrite rule flush should be performed when requested.
		 *
		 * A "hard" flush updates .htaccess (Apache) or web.config (IIS).
		 *
		 * @since 3.7.0
		 *
		 * @param bool $hard Whether to flush rewrite rules "hard". Default true.
		 */
		if ( ! $hard || ! apply_filters( 'flush_rewrite_rules_hard', true ) ) {
			return;
		}
		if ( function_exists( 'save_mod_rewrite_rules' ) )
			save_mod_rewrite_rules();
		if ( function_exists( 'iis7_save_url_rewrite_rules' ) )
			iis7_save_url_rewrite_rules();
	}

	/**
	 * Sets up the object's properties.
	 *
	 * The 'use_verbose_page_rules' object property will be set to true if the
	 * permalink structure begins with one of the following: '%postname%', '%category%',
	 * '%tag%', or '%author%'.
	 *
	 * @since 1.5.0
	 * @access public
	 */
	public function init() {
		$this->extra_rules = $this->non_wp_rules = $this->endpoints = array();
		$this->permalink_structure = get_option('permalink_structure'); // 如/%year%/%monthnum%/%day%/%postname%/
		$this->front = substr($this->permalink_structure, 0, strpos($this->permalink_structure, '%'));
		$this->root = '';

		if ( $this->using_index_permalinks() )
			$this->root = $this->index . '/';

		unset($this->author_structure);
		unset($this->date_structure);
		unset($this->page_structure);
		unset($this->search_structure);
		unset($this->feed_structure);
		unset($this->comment_feed_structure);
		$this->use_trailing_slashes = ( '/' == substr($this->permalink_structure, -1, 1) );

		// Enable generic rules for pages if permalink structure doesn't begin with a wildcard.
		/***  use_verbose_page_rules 是什么意思, 好象没什么用? */
		if ( preg_match("/^[^%]*%(?:postname|category|tag|author)%/", $this->permalink_structure) )
			 $this->use_verbose_page_rules = true;
		else
			$this->use_verbose_page_rules = false;
	}

	/**
	 * Sets the main permalink structure for the site.
	 *
	 * Will update the 'permalink_structure' option, if there is a difference
	 * between the current permalink structure and the parameter value. Calls
	 * WP_Rewrite::init() after the option is updated.
	 *
	 * Fires the 'permalink_structure_changed' action once the init call has
	 * processed passing the old and new values
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @param string $permalink_structure Permalink structure.
	 */
	public function set_permalink_structure($permalink_structure) {
		if ( $permalink_structure != $this->permalink_structure ) {
			$old_permalink_structure = $this->permalink_structure;
			update_option('permalink_structure', $permalink_structure);

			$this->init();

			/**
			 * Fires after the permalink structure is updated.
			 *
			 * @since 2.8.0
			 *
			 * @param string $old_permalink_structure The previous permalink structure.
			 * @param string $permalink_structure     The new permalink structure.
			 */
			do_action( 'permalink_structure_changed', $old_permalink_structure, $permalink_structure );
		}
	}

	/**
	 * Sets the category base for the category permalink.
	 *
	 * Will update the 'category_base' option, if there is a difference between
	 * the current category base and the parameter value. Calls WP_Rewrite::init()
	 * after the option is updated.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 * @param string $category_base Category permalink structure base.
	 */
	/** 
        如果要改变category	的美化链接的前缀, 就保存到db中
	默认美化链接是/category/cat1/cat11/..., 
	但是可以通过后台管理界面(options-permalink.php)设置category_base的值, 比如输入值为hello, 以后美化链接变成/hello/cat1/cat11/... 	
	*/	 
	public function set_category_base($category_base) {
		if ( $category_base != get_option('category_base') ) {
			update_option('category_base', $category_base);
			$this->init();
		}
	}

	/**
	 * Sets the tag base for the tag permalink.
	 *
	 * Will update the 'tag_base' option, if there is a difference between the
	 * current tag base and the parameter value. Calls WP_Rewrite::init() after
	 * the option is updated.
	 *
	 * @since 2.3.0
	 * @access public
	 *
	 * @param string $tag_base Tag permalink structure base.
	 */
	public function set_tag_base( $tag_base ) {
		if ( $tag_base != get_option( 'tag_base') ) {
			update_option( 'tag_base', $tag_base );
			$this->init();
		}
	}

	/**
	 * Constructor - Calls init(), which runs setup.
	 *
	 * @since 1.5.0
	 * @access public
	 *
	 */
	public function __construct() {
		$this->init();
	}
}
