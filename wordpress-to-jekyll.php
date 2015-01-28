<?php

$blogurls = array (
	"http://binfalse.de",
	"http://blog.binfalse.de",
	"https://binfalse.de",
	"https://blog.binfalse.de"
	);

/**
 * Wordpress to Jekyll
 *
 * A small conversion script that will convert a Wordpress export
 * into individual post files that you can use for a Jekyll
 * powered website.
 *
 * @author David Winter <i@djw.me>
 */
class WordpressToJekyll {
	
	protected $_export_file;
	protected $_post_dir;
	protected $_layout_post = 'post';
	protected $_layout_page = 'page';

	protected $_items;
	protected $_blogurls;
	
	protected $_linkrewrite;

	protected $_yaml;

	public function __construct($wordpress_xml_file, YamlDumperInterface $yaml, $posts, $pages, $attachments, $draft_posts, $draft_pages, $comments, $draft_comments, $blogurls, $rewrite_file)
	{
		$this->_export_file = $wordpress_xml_file;

		$this->_load_items();
		$this->_blogurls = $blogurls;
		
		$this->_linkrewrite = array ();

		$this->_yaml = $yaml;
		
		$this->_post_dir = rtrim($posts, '/');
		$this->_pages_dir = rtrim($pages, '/');
		$this->_attachments_dir = rtrim($attachments, '/');
		$this->_draft_post_dir = rtrim($draft_posts, '/');
		$this->_draft_pages_dir = rtrim($draft_pages, '/');
		$this->_comments_dir = rtrim($comments, '/');
		$this->_draft_comments_dir = rtrim($draft_comments, '/');
		$this->_rewrite_file = rtrim($rewrite_file);
	}

	protected function _setup_dirs()
	{
		if ( ! file_exists($this->_post_dir) && ! mkdir($this->_post_dir))
			return FALSE;
		if ( ! file_exists($this->_pages_dir) && ! mkdir($this->_pages_dir))
			return FALSE;
		if ( ! file_exists($this->_attachments_dir) && ! mkdir($this->_attachments_dir))
			return FALSE;
		if ( ! file_exists($this->_comments_dir) && ! mkdir($this->_comments_dir))
			return FALSE;
		if ( ! file_exists($this->_draft_post_dir) && ! mkdir($this->_draft_post_dir))
			return FALSE;
		if ( ! file_exists($this->_draft_pages_dir) && ! mkdir($this->_draft_pages_dir))
			return FALSE;
		if ( ! file_exists($this->_draft_comments_dir) && ! mkdir($this->_draft_comments_dir))
			return FALSE;
		

		return TRUE;
	}

	public function convert()
	{
		$this->_setup_dirs ();

		$pages = array ();
		
		foreach ($this->_items as $item)
		{
			$namespaces = $item->getNameSpaces(TRUE);
			$wp = $item->children($namespaces['wp']);
			switch ($wp->post_type)
			{
				case "post":
					$post = $this->_post_array($item);
					$formatted_post = $this->_format_post($post);
					$this->_write_post($post, $formatted_post);
					if (count ($post["comments"]))
						$this->_write_comments($post["comments"], $post['post_id']);
					break;
				case "page":
					$page = $this->_pages_array($item);
					$pages["" . $page["id"]] = $page;
					break;
				case "attachment":
					$this->_process_attachment ($item);
					break;
				default:
					echo "ignoring post type " . $wp->post_type . "\n";
			}
		}
		
		foreach ($pages as $page)
		{
			$path = $this->_get_page_path ($page, $pages);
			$formatted_page = $this->_format_post($page);
			$this->_write_page($page, $path, $formatted_page);
			$page['post_id'] = "/".$path;
			if (count ($page["comments"]))
				$this->_write_comments($page["comments"], $page['post_id']);
		}
		
		$this->_write_rewrite();
	}
	
	protected function _get_page_path ($page, $pages)
	{
		if (!$page['parent'] || $page['parent'] == 0)
			return $page['filename'];
		else
			return $this->_get_page_path ($pages["" . $page['parent']], $pages)."/".$page['filename'];
	}

	protected function _load_items()
	{
		$xml = simplexml_load_file($this->_export_file, 'SimpleXMLElement', LIBXML_NOCDATA);
		$this->_items = $xml->channel->item;
	}
	
	protected function _jekyll_tags ($tagname)
	{
		return preg_replace ("/[^A-Za-z0-9]/", '', $tagname);
	}
	
	protected function _process_attachment($item)
	{
		$attachment = array ();
		$namespaces = $item->getNameSpaces(TRUE);
		$wp = $item->children($namespaces['wp']);
		
		$this->_write_attachment ($wp->attachment_url);
		
		$baseUrl = str_replace (basename ($wp->attachment_url), "", $wp->attachment_url);
		
		foreach ($wp->postmeta as $postmeta)
		{
			$wppm = $postmeta->children($namespaces['wp']);
			//echo ((string)$wppm->meta_key)."\n";
			if ("_wp_attachment_metadata" == (string)$wppm->meta_key)
			{
				$meta = unserialize ((string)$wppm->meta_value);
				//print_r($meta);
				//echo str_replace (basename ($wp->attachment_url), "", $wp->attachment_url) ."\n";
				foreach ($meta["sizes"] as $size)
				{
					$this->_write_attachment ($baseUrl.$size["file"]);
				}
			}
		}
		
	}
	
	protected function _process_content ($content)
	{
	
		# site specific stuff
		$content = str_replace ($this->_blogurls, "", $content);
		
		$content = str_replace("\\", '\\\\', $content);
		$content = str_replace("<!--more-->", '', $content);
		# codecolorer
		$content = preg_replace("/\[cci[^\]]*\]/", ' `', $content);
		$content = str_replace("[/cci]", '` ', $content);
		# multiline code
		preg_match_all('@\[cc[^\]]*\].+?\[\/cc\]@is', $content, $matches);
		foreach ($matches as $ma)
			foreach ($ma as $m)
			{
				#var_dump ($m);
				#echo "\n\n";
				$code = preg_replace("/\[cc[^\]]*lang=['\"]([^\"']+)['\"][^\]]*\]\s*/is", '{% highlight $1 %}'."\n", $m);
				$code = preg_replace("/\[cc[^\]]*\]\s*/is", '{% highlight bash %}'."\n", $code);
				$code = str_replace("rsplus", 'r', $code);
				$code = preg_replace("/\s*\[\/cc\]/", "\n{% endhighlight %}", $code);
				#$code = preg_replace ("/^/m", "    ", $code);
				$content = str_replace($m, "\n\n".$code."\n\n", $content);
			}
		
		# latex
		$content = str_replace("[latex]", '$$', $content);
		$content = str_replace("[/latex]", '$$', $content);
		
		# images
		$content = preg_replace('@\[caption[^\]]*align="([^"]*)"[^\]]*\]<a[^>]*href="([^"]*)"[^>]*><img[^/]*src="([^"]+)"[^/]*/></a>([^\[]*)\[/caption\]@is', '{% include image.html align="$1" url="$2" img="$3" title="$4" caption="$4" %}', $content);
		
		
		return $content;
	}
	
	protected function _process_comments ($comments)
	{
		$c = array ();
		foreach ($comments as $comment)
		{
			$cur = array ();
			$cur["meta"]["name"] = (string) $comment->comment_author;
			// we do not include mail addresses $cur["meta"]["comment_author_email"] = (string)$comment->comment_author_email;
			$cur["meta"]["link"] = (string)$comment->comment_author_url;
			$cur["meta"]["date"] = (string)$comment->comment_date;
			$cur["meta"]["comment"] = $this->_process_content((string)$comment->comment_content);
			$cur["comment_approved"] = (string)$comment->comment_approved;
			$cur["content"] = "";
			$c[] = $cur;
		}
		return $c;
	}
	
	
	protected function _pages_array($item)
	{
		$page = array();
		
		$namespaces = $item->getNameSpaces(TRUE);
		
		$wp = $item->children($namespaces['wp']);
		
		$page['filename'] = $wp->post_name;
		$page['id'] = $wp->post_id;
		// the following line is just for the records. comments for pages seems to make no sense in jekyll?
		$page['comments'] = $this->_process_comments ($wp->comment);
		$page['parent'] = $wp->post_parent;
		$page['meta']['layout'] = $this->_layout_page;
		
		$page['meta']['title'] = (string) $item->title;
		
		$tags = array();
		$cats = array ();
		
		if ($item->category)
		{
			foreach ($item->category as $tag)
			{
				if ( (string) $tag['domain'] === 'post_tag')
					$tags[] = $this->_jekyll_tags ((string) $tag);
				else if ( (string) $tag['domain'] === 'category')
					$cats[] = $this->_jekyll_tags ((string) $tag);
			}
		}
		
		if ( ! empty($tags))
			$page['meta']['tags'] = $tags;
		if ( ! empty($cags))
			$page['meta']['categories'] = $cats;
		
		if ($wp->status == "draft")
			$page['draft'] = TRUE;
		else
			$page['draft'] = FALSE;
		
		$content = $item->children($namespaces['content']);
		
		$page['content'] = $this->_process_content ($content->encoded);
		
		return $page;
	}

	protected function _post_array($item)
	{
		$post = array();

		$namespaces = $item->getNameSpaces(TRUE);

		$wp = $item->children($namespaces['wp']);

		$post['filename'] = sprintf('%s-%s.md',
			date('Y-m-d', strtotime($wp->post_date)),
			$wp->post_name
		);
		$post['comments'] = $this->_process_comments ($wp->comment);
		$post['post_id'] = sprintf('/%s/%s',
			date('Y/m/d', strtotime($wp->post_date)),
			$wp->post_name
		);

		$post['meta']['layout'] = $this->_layout_post;

		$post['meta']['title'] = (string) $item->title;
		
		$tags = array();
		$cats = array ();

		if ($item->category)
		{
			foreach ($item->category as $tag)
			{
				if ( (string) $tag['domain'] === 'post_tag')
					$tags[] = $this->_jekyll_tags ((string) $tag);
				else if ( (string) $tag['domain'] === 'category')
					$cats[] = $this->_jekyll_tags ((string) $tag);
			}
		}

		if ( ! empty($tags))
			$post['meta']['tags'] = $tags;
		if ( ! empty($cats))
			$post['meta']['categories'] = $cats;
		
		if ($wp->status == "draft")
			$post['draft'] = TRUE;
		else
		{
			$post['draft'] = FALSE;
		
			$oldlink = sprintf('/%s/%s',
			date('Y/m', strtotime($wp->post_date)),
			$wp->post_name
			);
			$this->_linkrewrite[$oldlink] = $post['post_id'];
		}
		
		$content = $item->children($namespaces['content']);

		$post['content'] = $this->_process_content ($content->encoded);

		return $post;
	}

	protected function _format_post($post)
	{
		$meta = $this->_yaml->dump($post['meta']);

		$post_content = <<<EOT
---
{$meta}
---

{$post['content']}

EOT;
		
		return $post_content;
	}
	
	protected function _write_post($post, $formatted)
	{
		if ($post["draft"])
			return file_put_contents($this->_draft_post_dir.'/'.$post['filename'], $formatted);
		else
			return file_put_contents($this->_post_dir.'/'.$post['filename'], $formatted);
	}
	
	protected function _write_page($page, $path, $formatted)
	{
		$directory = ($page["draft"] ? $this->_draft_pages_dir : $this->_pages_dir).'/'.$path;
		if ( ! file_exists($directory) && ! mkdir($directory, 0777, true))
		{
			echo "error creating dir ".$directory."\n";
			return;
		}
		return file_put_contents($directory.'/index.html', $formatted);
	}
	
	protected function _write_comments ($comments, $post_id)
	{
		foreach ($comments as $comment)
		{
			$comment["meta"]["post_id"] = $post_id;
			$formatted = $this->_format_post($comment);
			$comment_file = $post_id."-".preg_replace ("/[^A-Za-z0-9]/", '-', $comment["meta"]["date"]).".md";
			$target = $this->_comments_dir.'/'.$comment_file;
			if ($comment["comment_approved"] == 0)
				$target = $this->_draft_comments_dir.'/'.$comment_file;
			$directory = dirname ($target);
			if ( ! file_exists($directory) && ! mkdir($directory, 0777, true))
			{
				echo "error creating dir ".$directory."\n";
				return;
			}
			
			file_put_contents($target, $formatted);
		}
	}
	
	protected function _write_rewrite ()
	{
		$contentApache = "";
		$contentNginx = "location / {\n";
		foreach ($this->_linkrewrite as $old => $new)
		{
			$contentApache .= "RewriteRule ".substr ($old, 1)."(.*) ".$new."$1 [R=301,NC]\n";
			$contentNginx .= "\trewrite ".substr ($old, 1)."(.*) ".$new."$1 redirect;\n";
		}
		$contentNginx .= "}\n";
		file_put_contents($this->_rewrite_file.".apache", $contentApache);
		file_put_contents($this->_rewrite_file.".nginx", $contentNginx);
	}
	

	protected function _write_attachment($attachment_link)
	{
		$name = preg_replace ("/^.*wp-content\/uploads\//", '', $attachment_link);
		echo "downloading " . $attachment_link . " to " . $name . " in ". $this->_attachments_dir . "\n";
		$target = $this->_attachments_dir.'/'.$name;
		$directory = dirname ($target);
		if ( ! file_exists($directory) && ! mkdir($directory, 0777, true))
		{
			echo "error creating dir ".$directory."\n";
			return;
		}
		//return;
		if (file_exists ($target))
		{
			echo $target . " exists - skipping\n";
			return;
		}
		return file_put_contents($target, fopen($attachment_link, 'r'));
	}

}

interface YamlDumperInterface {
	
	public function dump($data);

}

$sfyaml = __DIR__.'/vendor/yaml/lib/sfYaml.php';

if ( ! file_exists($sfyaml))
{
	throw new Exception('Symfony yaml was not found. You may need to initialise the git submodules: git submodule update --init --recursive');
}

require($sfyaml);

class YamlDumper implements YamlDumperInterface {
	
	public function dump($data)
	{
		$yaml = new sfYaml();

		return $yaml->dump($data);
	}

}

$file = (isset($argv[1])) ? $argv[1] : getcwd().'/export.xml';
$posts = (isset($argv[2])) ? $argv[2] : getcwd().'/posts';
$pages = (isset($argv[3])) ? $argv[3] : getcwd().'/pages';
$attachments = (isset($argv[4])) ? $argv[4] : getcwd().'/attachments';
$draft_posts = (isset($argv[5])) ? $argv[5] : getcwd().'/draft_posts';
$draft_pages = (isset($argv[6])) ? $argv[6] : getcwd().'/draft_pages';
$comments = (isset($argv[7])) ? $argv[7] : getcwd().'/comments';
$draft_comments = (isset($argv[8])) ? $argv[8] : getcwd().'/draft_comments';
$rewrite_file = (isset($argv[9])) ? $argv[9] : getcwd().'/url_rewrite';


$convert = new WordpressToJekyll($file, new YamlDumper, $posts, $pages, $attachments, $draft_posts, $draft_pages, $comments, $draft_comments, $blogurls, $rewrite_file);
$convert->convert();
