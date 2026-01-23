<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package shishcafe
 */

get_header();
?>

<main id="primary" class="site-main">

    <section class="error-404 not-found">
        <!-- .page-header -->

        <div class="simple-404-container" style="text-align: center; padding: 50px 20px;">
            <h1 style="font-size: 80px; margin: 0; color: #CAAB68;">404</h1>
            <p style="font-size: 24px; margin: 10px 0 30px;">Page Not Found</p>
            <a href="<?php echo esc_url(home_url('/')); ?>" style="background-color: #CAAB68;
              color: #fff;
              padding: 15px 40px;
              border-radius: 30px;
              text-decoration: none;
              display: inline-block;
              font-weight: bold;
              transition: all 0.3s ease;"
                onmouseover="this.style.backgroundColor='#B89756'; this.style.transform='translateY(-2px)'"
                onmouseout="this.style.backgroundColor='#CAAB68'; this.style.transform='none'">
                Go to Home Page
            </a>
        </div>
    </section><!-- .error-404 -->

</main><!-- #main -->

<?php
get_footer();