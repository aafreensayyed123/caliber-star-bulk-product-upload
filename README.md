# Bulk CSV Importer with Image Uploads

**Plugin Name:** Bulk CSV Importer with Image Uploads  
**Description:** A plugin to bulk upload entries from a CSV file into the "products" custom post type, including uploading images and assigning taxonomies.  
**Version:** 1.2  
**Author:** Aafreen Sayyed

## Description

This WordPress plugin allows you to upload and import a bulk set of product entries via a CSV file into a custom post type (`products`). The plugin supports the following features:

- Importing meta fields and assigning taxonomy terms.
- Uploading images from URLs and associating them with products.
- Setting featured images and saving gallery image meta data.
- Extending REST API responses to include custom fields and image URLs.

## Features

- Upload CSV files to import multiple products at once.
- Automatically handle image uploads from external URLs and set featured images.
- Assign products to taxonomies such as `brands`.
- Extend REST API responses with custom meta fields and image URLs for easier API integrations.

## Requirements

- WordPress version 5.0 or higher.
- PHP version 7.0 or higher.

## Installation

1. Download the plugin as a `.zip` file or clone the repository.
2. Log in to your WordPress admin dashboard.
3. Go to **Plugins > Add New > Upload Plugin** and upload the `.zip` file.
4. Activate the plugin after installation.
5. Navigate to **Tools > Bulk CSV Importer** to start importing.

## Usage

### CSV File Format

- The first row of the CSV file must contain column headers.
- Supported columns:
  - **product-title**: The title of the product (required).
  - **main-image-primary**: URL of the primary image for the product (optional).
  - **main-image-secondary**: URL of the secondary image for the product (optional).
  - **featured-image**: URL of the featured image (optional).
  - Any additional columns will be imported as meta fields.

### Steps to Import

1. Navigate to **Tools > Bulk CSV Importer** in the WordPress admin dashboard.
2. Upload your CSV file.
3. Click "Upload and Import" to start the import process.
4. Upon completion, you will be redirected back to the tool with a summary of imported rows.

### Taxonomy Support

- Products are automatically assigned the "rolex" term in the `brands` taxonomy during import. You can modify this behavior in the plugin code.

## REST API Integration

The plugin extends the REST API for the `products` custom post type. Additional fields added to the API response:

- **featured_media_url**: URL of the featured image.
- **main_image_primary_url**: URL of the primary image.
- **gallery_images_urls**: Array of URLs for gallery images.

### Example REST API Response

```json
{
  "id": 123,
  "title": "Sample Product",
  "featured_media_url": "https://example.com/uploads/featured.jpg",
  "main_image_primary_url": "https://example.com/uploads/main-primary.jpg",
  "gallery_images_urls": [
    "https://example.com/uploads/gallery1.jpg",
    "https://example.com/uploads/gallery2.jpg"
  ]
}
```
