# Wordpress to Jekyll

A simple script that will convert posts from a Wordpress export into Jekyll friendly post files.

It will pull out the title and tags from posts, along with the main post content and then save them into the correct timestamp named files based on the post date and post title slug.

This will ensure if you're moving from Wordpress to Jekyll, your old links will work so long as you include the correct permalink structure in your `_config.yml` file.

## Dependencies
* Requires PHP 5.3
* you need the [static comments plugin](https://github.com/mpalmer/jekyll-static-comments) in your jekyll installation for the comments feature
* for the images you need another plugin. tba.

## Install

	git clone git://github.com/binfalse/wordpress-to-jekyll.git
	cd wordpress-to-jekyll/
	git submodule update --init --recursive

Download your Wordpress export to the same directory, and then rename it to `export.xml`, then run:

	php wordpress-to-jekyll.php

This will create the following directories:

* `posts`: directory with all published posts, copy it to your `_posts` directory in your jekyll installation
* `pages`: directory with all published pages, copy it the root of your jekyll installation
* `attachments`: directory with all attachments uploaded to wordpress, copy it to `assets/media/wp-content/uploads`
* `comments`: directory with all published comments, copy it to the `_comments` directory in your jekyll installation
* `draft_posts`: directory with all unpublished posts
* `draft_comments`: directory with all unpublished comments
* `draft_pages`: directory with all unpublished pages
* `url_rewrite`: a file containing optional `.htaccess` rewrite rules that might help maintaining link structures

I do not care about the unpublished things, it's up to you whether you finalise them or put it in a `_drafts` directory or whatever..

To keep the links to the attachments valid I copy all attachments to `assets/media/wp-content/uploads` and use an `.htaccess` file to rewrite the URLs. There is a template in this repository that you can use: `htaccess-template`

    RewriteEngine on
    RewriteRule ^wp-content/(.*)$ /assets/media/wp-content/$1 [R,NC]

This will rewrite URLs such as `SITE/wp-content/uploads/YEAR/MONTH/FILE` to `SITE/assets/media/wp-content/uploads/YEAR/MONTH/FILE`. Thus, external links stay valid.
