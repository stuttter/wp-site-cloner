# WP Site Cloner

Use an existing site's content to populate a new site.

WP Site Cloner allows the copying of a site's content with just 1 function.

# Installation

* Download and install using the built in WordPress plugin installer.
* Activate in the "Plugins" area of your admin by clicking the "Activate" link.
* No further setup or configuration is necessary.

# FAQ

### Does this create new database tables?

No. There are no new database tables with this plugin.

### Does this modify existing database tables?

No. All of WordPress's core database tables remain untouched.

### How do I use this?

Probably something like:

```
wp_clone_site( array(
	'domain'        => 'src.wordpress-develop.dev',
	'path'          => '/paul/',
	'title'         => 'Paul the Dog',
	'from_site_id'  => 35,
	'to_network_id' => 1,
	'meta'          => array(
		'public' => 1
	)
) );
```

### Where can I get support?

The WordPress support forums: https://wordpress.org/tags/wp-site-cloner/

### Can I contribute?

Yes, please!
