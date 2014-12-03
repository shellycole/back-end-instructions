<?php
/**
 * The main template file.
 *
 * This is the template file for the Back End Instructions plugin.  It's based off the generic
 * index.php file from the Twenty Eleven theme, but has some code in it that makes it work
 * with the plugin.  You may edit this at will.  Save it in place of your theme's current
 * index.php file.
 *
 * You ONLY need to put this in your theme IF you want your instructions to show on the front 
 * end of the site.  You have to edit the plugin settings to make your WordPress install
 * use this template file properly.
 *
 * PLEASE NOTE: you should save a backup of the current index.php file in your existing theme.
 * If you ever want or need to get rid of the Back End Instructions plugin, you'll want to put your
 * theme's original index.php file back in place of this one.
 *
 * @package WordPress
 * @subpackage Twenty_Eleven
 */

get_header(); ?>

		<div id="primary">
			<div id="content" role="main">

			<?php $ids = bei_instructions_query_filter();
				  $ids = array($ids);
				  $paged = (get_query_var('paged')) ? get_query_var('paged') : 0;
				  $bei_query = new WP_Query(array('post_type' => 'instructions', 'post__in' => $ids[0], 'paged' => $paged));				
			     
				  if ( $bei_query->have_posts() ) : ?>
				  
				  <?php twentyeleven_content_nav( 'nav-above' ); ?>

			<?php while ( $bei_query->have_posts() ) : $bei_query->the_post(); ?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>

		<div class="entry-meta">
			<?php twentyeleven_posted_on(); ?>
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php /* Because we had to use special tags for shortcodes, because of how the help tabs deal with them
				 on the back end, the next few lines help fix all of that fo display on the front end.*/
			  $content = get_the_content(); 
			  $content = preg_replace_callback( "/(\{\{)(.*)(\}\})/", create_function('$matches', 'return "[" . $matches[2] . "]";'), $content );
			  echo apply_filters('the_content', $content);
		?>
		<?php wp_link_pages( array( 'before' => '<div class="page-link"><span>' . __( 'Pages:', 'twentyeleven' ) . '</span>', 'after' => '</div>' ) ); ?>
	</div><!-- .entry-content -->

	<footer class="entry-meta">
		<?php edit_post_link( __( 'Edit', 'twentyeleven' ), '<span class="edit-link">', '</span>' ); ?>

		<?php if ( get_the_author_meta( 'description' ) && ( ! function_exists( 'is_multi_author' ) || is_multi_author() ) ) : // If a user has filled out their description and this is a multi-author blog, show a bio on their entries ?>
		<div id="author-info">
			<div id="author-avatar">
				<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'twentyeleven_author_bio_avatar_size', 68 ) ); ?>
			</div><!-- #author-avatar -->
			<div id="author-description">
				<h2><?php printf( __( 'About %s', 'twentyeleven' ), get_the_author() ); ?></h2>
				<?php the_author_meta( 'description' ); ?>
				<div id="author-link">
					<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" rel="author">
						<?php printf( __( 'View all posts by %s <span class="meta-nav">&rarr;</span>', 'twentyeleven' ), get_the_author() ); ?>
					</a>
				</div><!-- #author-link	-->
			</div><!-- #author-description -->
		</div><!-- #author-info -->
		<?php endif; ?>
	</footer><!-- .entry-meta -->
</article><!-- #post-<?php the_ID(); ?> -->


				<?php endwhile; ?>	
				
				<div class="alignleft"><?php next_posts_link('Previous Entries'); ?></div>
				<div class="alignright"><?php previous_posts_link('Next Entries'); ?></div>			

			<?php else : ?>

				<article id="post-0" class="post no-results not-found">
					<header class="entry-header">
						<h1 class="entry-title"><?php _e( 'Nothing Found', 'twentyeleven' ); ?></h1>
					</header><!-- .entry-header -->

					<div class="entry-content">
						<p><?php _e( 'Apologies, but no results were found for the requested archive. Perhaps searching will help find a related post.', 'twentyeleven' ); ?></p>
						<?php get_search_form(); ?>
					</div><!-- .entry-content -->
				</article><!-- #post-0 -->

			<?php endif; 
				  wp_reset_query(); ?>
			
			<div class="navigation">
				<h3 class="assistive-text"><?php _e( 'Post navigation', 'twentyeleven' ); ?></h3>				
			
			<!-- .navigation -->
			</div>

			</div><!-- #content -->
		</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>