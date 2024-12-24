<?php
/**
 * Plugin Name: Bulk CSV Importer with Image Uploads
 * Description: A plugin to bulk upload entries from a CSV file into the "products" custom post type, including uploading images.
 * Version: 1.2
 * Author: Aafreen Sayyed
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BulkCSVImporter
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_process_csv_upload', [$this, 'process_csv_upload']);
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'Bulk CSV Importer',
            'Bulk CSV Importer',
            'manage_options',
            'bulk-csv-importer',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Bulk CSV Importer</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_csv_upload">
                <?php wp_nonce_field('bulk_csv_importer_nonce', 'bulk_csv_importer_nonce_field'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csv_file">Upload CSV File</label>
                        </th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload and Import'); ?>
            </form>
        </div>
        <?php
    }

    public function process_csv_upload()
    {
        if (
            !isset($_POST['bulk_csv_importer_nonce_field']) ||
            !wp_verify_nonce($_POST['bulk_csv_importer_nonce_field'], 'bulk_csv_importer_nonce')
        ) {
            wp_die('Nonce verification failed.');
        }

        if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $file = $_FILES['csv_file'];

        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_die('Invalid file type. Please upload a CSV file.');
        }

        $file_path = $file['tmp_name'];
        if (($handle = fopen($file_path, 'r')) === false) {
            wp_die('Unable to open the uploaded file.');
        }

        $headers = fgetcsv($handle); // Read the header row
        if (!$headers) {
            wp_die('Invalid CSV file format.');
        }

        $row_count = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            $post_data = array(
                'post_title' => $row['product-title'] ?? 'Untitled Product',
                'post_status' => 'publish',
                'post_type' => 'products',
            );

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                foreach ($row as $meta_key => $meta_value) {
                    if (!empty($meta_value)) {
                        // Handle image fields
                        if (in_array($meta_key, ['main-image-primary', 'main-image-secondary', 'featured-image'])) {
                            $image_id = $this->upload_image_from_url($meta_value, $post_id);

                            if ($image_id) {
                                if ($meta_key === 'main-image-primary') {
                                    // Set as featured image and save meta
                                    set_post_thumbnail($post_id, $image_id);
                                    update_post_meta($post_id, sanitize_key($meta_key), $image_id);
                                } elseif ($meta_key === 'featured-image') {
                                    // Set as featured image if not already set
                                    set_post_thumbnail($post_id, $image_id);
                                } else {
                                    // Save image ID as meta field
                                    update_post_meta($post_id, sanitize_key($meta_key), $image_id);
                                }
                            }
                        } else {
                            // Save other meta fields
                            update_post_meta($post_id, sanitize_key($meta_key), sanitize_text_field($meta_value));
                        }
                    }
                }

                // Assign the "rolex" term to the "brands" taxonomy
                $term = 'rolex';
                $taxonomy = 'brands';

                if (!term_exists($term, $taxonomy)) {
                    wp_insert_term($term, $taxonomy);
                }

                wp_set_object_terms($post_id, $term, $taxonomy);
            }

            $row_count++;
        }

        fclose($handle);

        wp_redirect(admin_url('tools.php?page=bulk-csv-importer&rows_imported=' . $row_count));
        exit;
    }

    /**
     * Upload an image from a URL and attach it to the given post.
     *
     * @param string $image_url The URL of the image to upload.
     * @param int    $post_id   The ID of the post to attach the image to.
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    private function upload_image_from_url($image_url, $post_id)
    {
        // Download the image
        $image_data = wp_remote_get($image_url);

        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            return false;
        }

        $image_contents = wp_remote_retrieve_body($image_data);
        $image_type = wp_remote_retrieve_header($image_data, 'content-type');

        // Validate image type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($image_type, $allowed_types)) {
            return false;
        }

        // Get the file extension
        $file_extension = substr($image_type, strpos($image_type, '/') + 1);
        $filename = 'image-' . wp_generate_password(8, false) . '.' . $file_extension;

        // Upload the image to the WordPress uploads directory
        $upload = wp_upload_bits($filename, null, $image_contents);

        if ($upload['error']) {
            return false;
        }

        // Create an attachment post for the image
        $attachment = array(
            'post_mime_type' => $image_type,
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Generate image metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }
}

// REST API Filter to include image URLs
add_filter('rest_prepare_products', function ($response, $post, $request) {
    // Retrieve the `main_image_primary` field value (media ID)
    $main_image_primary_id = get_post_meta($post->ID, 'main-image-primary', true);

    if ($main_image_primary_id) {
        // Get the URL of the attachment from the media ID
        $main_image_primary_url = wp_get_attachment_url($main_image_primary_id);
        if ($main_image_primary_url) {
            $response->data['main_image_primary_url'] = $main_image_primary_url;
        }
    }

    // Get the URL of the featured image
    $featured_image_id = get_post_thumbnail_id($post->ID);
    if ($featured_image_id) {
        $featured_image_url = wp_get_attachment_url($featured_image_id);
        $response->data['featured_media_url'] = $featured_image_url;
    }

    // Add other image fields if needed
    $image_fields = ['main-image-secondary', 'featured-image'];
    foreach ($image_fields as $meta_key) {
        $image_id = get_post_meta($post->ID, $meta_key, true);
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            $response->data[$meta_key . '_url'] = $image_url;
        }
    }

    return $response;
}, 10, 3);

add_action('rest_api_init', function () {
    // Add the featured media URL
    register_rest_field('products', 'featured_media_url', [
        'get_callback' => function ($post) {
            $media_id = get_post_thumbnail_id($post['id']);
            return $media_id ? wp_get_attachment_url($media_id) : null;
        },
        'schema' => [
            'description' => 'URL of the featured media',
            'type' => 'string',
            'context' => ['view', 'edit'],
        ],
    ]);

    // Add the main image primary URL
    register_rest_field('products', 'main_image_primary_url', [
        'get_callback' => function ($post) {
            $media_id = get_post_meta($post['id'], 'main-image-primary', true);
            return $media_id ? wp_get_attachment_url($media_id) : null;
        },
        'schema' => [
            'description' => 'URL of the primary main image',
            'type' => 'string',
            'context' => ['view', 'edit'],
        ],
    ]);

    // Add the gallery images URLs
    register_rest_field('products', 'gallery_images_urls', [
        'get_callback' => function ($post) {
            $gallery = get_post_meta($post['id'], 'gallery-images', true);
            if (!is_array($gallery)) {
                $gallery = [];
            }
            $urls = array_map('wp_get_attachment_url', $gallery);
            return array_filter($urls); // Filter out invalid or empty URLs
        },
        'schema' => [
            'description' => 'Array of gallery image URLs',
            'type' => 'array',
            'items' => [
                'type' => 'string',
            ],
            'context' => ['view', 'edit'],
        ],
    ]);
});


// Initialize the plugin
new BulkCSVImporter();