<?php
/**
 * PDF Export Handler
 * Exports post with optional replies to PDF using TCPDF
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

// Get post ID
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if TCPDF is installed
if (!file_exists(__DIR__ . '/vendor/tcpdf/tcpdf.php')) {
    die('TCPDF library not installed. Please download TCPDF and extract it to vendor/tcpdf/. See vendor/README.md for instructions.');
}

require_once __DIR__ . '/vendor/tcpdf/tcpdf.php';

// Increase execution time for large PDFs
set_time_limit(60);

// Fetch post data
try {
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.name AS subcategory_name,
            c.name AS category_name
        FROM posts p
        JOIN subcategories s ON p.subcategory_id = s.id
        JOIN categories c ON s.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die('Post not found.');
    }

    // Fetch files attached to post
    $stmt = $pdo->prepare("SELECT original_filename FROM files WHERE post_id = ? ORDER BY uploaded_at ASC");
    $stmt->execute([$post_id]);
    $post_files = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch replies
    $stmt = $pdo->prepare("SELECT * FROM replies WHERE post_id = ? ORDER BY created_at ASC");
    $stmt->execute([$post_id]);
    $replies = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die('Database error occurred.');
}

// Helper function to format timestamp
function format_timestamp_pdf($timestamp) {
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

// Helper function to render HTML content to PDF with proper formatting
function render_html_to_pdf($html_content, &$pdf) {
    // First decode HTML entities
    $html = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');

    // Replace common HTML entities
    $html = str_replace('&nbsp;', ' ', $html);
    $html = str_replace('&ndash;', '–', $html);
    $html = str_replace('&mdash;', '—', $html);
    $html = str_replace('&ldquo;', '"', $html);
    $html = str_replace('&rdquo;', '"', $html);
    $html = str_replace('&lsquo;', "'", $html);
    $html = str_replace('&rsquo;', "'", $html);
    $html = str_replace('&hellip;', '...', $html);

    // Get page dimensions
    $page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    $text_width = $page_width - 20; // Leave 20mm margin

    // Process content in order
    $parts = preg_split('/(<img[^>]*>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

    foreach ($parts as $part) {
        if (preg_match('/<img[^>]*>/i', $part)) {
            // This is an image tag - process it
            process_image_tag($part, $pdf);
            // Space is added inside process_image_tag function
        } elseif (!empty(trim($part))) {
            // This is text content - process it
            // Remove remaining img tags just in case
            $part = preg_replace('/<img[^>]*>/i', '', $part);

            // Process lists
            $part = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) use ($pdf, $text_width) {
                return process_list_text($matches[1], false, $pdf, $text_width);
            }, $part);

            $part = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) use ($pdf, $text_width) {
                return process_list_text($matches[1], true, $pdf, $text_width);
            }, $part);

            // Process other HTML tags
            $part = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', "\n\n### $1 ###\n\n", $part);
            $part = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "\n$1\n\n", $part);
            $part = preg_replace('/<br[^>]*>/i', "\n", $part);
            $part = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $part);
            $part = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $part);
            $part = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $part);

            // Clean up remaining HTML and write text
            $text = strip_tags($part);
            $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
            $text = trim($text);

            if (!empty($text)) {
                $pdf->SetFont('helvetica', '', 11);
                $pdf->MultiCell($text_width, 5, $text, 0, 'L');
                $pdf->Ln(2);
            }
        }
    }
}

// Helper function to process DOM nodes recursively
function process_dom_node($node, &$pdf, $depth = 0) {
    foreach ($node->childNodes as $child) {
        switch ($child->nodeName) {
            case 'h1':
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->Ln(5);
                $pdf->Cell(0, 8, $child->textContent, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(3);
                break;

            case 'h2':
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Ln(5);
                $pdf->Cell(0, 7, $child->textContent, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(3);
                break;

            case 'h3':
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Ln(5);
                $pdf->Cell(0, 6, $child->textContent, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(3);
                break;

            case 'h4':
            case 'h5':
            case 'h6':
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Ln(5);
                $pdf->Cell(0, 6, $child->textContent, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(3);
                break;

            case 'p':
                process_inline_content($child, $pdf, $depth);
                $pdf->Ln(8);
                break;

            case 'ul':
                process_list($child, $pdf, $depth, false);
                break;

            case 'ol':
                process_list($child, $pdf, $depth, true);
                break;

            case 'img':
                process_image($child, $pdf);
                break;

            case 'br':
                $pdf->Ln(3);
                break;

            case 'blockquote':
                $pdf->SetFont('helvetica', 'I', 11);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(15, 5, '>', 0, 0);
                process_inline_content($child, $pdf, $depth);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(8);
                break;

            case 'div':
            case 'span':
                process_dom_node($child, $pdf, $depth);
                break;

            case 'pre':
                $pdf->SetFont('courier', '', 10);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell(0, 5, $child->textContent, 0, 1, 'L', true);
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(5);
                break;

            case 'table':
                process_table($child, $pdf);
                break;

            case '#text':
                if (trim($child->textContent)) {
                    $pdf->Cell(0, 5, trim($child->textContent), 0, 1, 'L');
                }
                break;
        }
    }
}

// Helper function to process inline content (text inside paragraphs, etc.)
function process_inline_content($node, &$pdf, $depth) {
    $text = '';
    foreach ($node->childNodes as $child) {
        switch ($child->nodeName) {
            case 'strong':
            case 'b':
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Write(5, $child->textContent);
                $pdf->SetFont('helvetica', '', 11);
                break;

            case 'em':
            case 'i':
                $pdf->SetFont('helvetica', 'I', 11);
                $pdf->Write(5, $child->textContent);
                $pdf->SetFont('helvetica', '', 11);
                break;

            case 'u':
                $pdf->SetFont('helvetica', 'U', 11);
                $pdf->Write(5, $child->textContent);
                $pdf->SetFont('helvetica', '', 11);
                break;

            case 'code':
                $pdf->SetFont('courier', '', 10);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->Cell(0, 5, $child->textContent, 0, 1, 'L', true);
                $pdf->SetFont('helvetica', '', 11);
                break;

            case 'a':
                $pdf->SetTextColor(0, 0, 255);
                $pdf->SetFont('helvetica', 'U', 11);
                $pdf->Write(5, $child->textContent);
                $pdf->SetFont('helvetica', '', 11);
                $pdf->SetTextColor(0, 0, 0);
                break;

            case 'br':
                $pdf->Ln(3);
                break;

            case '#text':
                $content = trim($child->textContent);
                if (!empty($content)) {
                    $pdf->Write(5, $content);
                }
                break;

            default:
                process_dom_node($child, $pdf, $depth);
        }
    }
}

// Helper function to process lists with proper indentation
function process_list($list_node, &$pdf, $depth, $ordered = false) {
    $pdf->Ln(5);
    $list_items = $list_node->getElementsByTagName('li');
    $number = 1;

    foreach ($list_items as $item) {
        // Calculate indentation (increase for deeper nesting)
        $indent = 10 + ($depth * 20); // 10px base + 20px per nesting level

        if ($ordered) {
            $prefix = $number . '. ';
            $number++;
        } else {
            // Different bullet styles for different levels
            switch ($depth) {
                case 0:
                    $prefix = '• ';
                    break;
                case 1:
                    $prefix = '◦ ';
                    break;
                case 2:
                    $prefix = '▪ ';
                    break;
                default:
                    $prefix = '• ';
                    break;
            }
        }

        // Process the list item content (excluding nested lists)
        $text_content = '';
        $nested_lists = [];

        foreach ($item->childNodes as $child) {
            if ($child->nodeName === 'ul' || $child->nodeName === 'ol') {
                $nested_lists[] = $child;
            } elseif ($child->nodeName === '#text') {
                $text_content .= trim($child->textContent);
            } elseif ($child->nodeName === 'p') {
                $text_content .= trim($child->textContent);
            } else {
                // Handle other inline elements
                $text_content .= trim($child->textContent);
            }
        }

        // Set font based on depth
        if ($depth === 0) {
            $pdf->SetFont('helvetica', '', 11);
        } else {
            $pdf->SetFont('helvetica', '', 10);
        }

        // Add indentation cell
        $pdf->Cell($indent, 5, '', 0, 0);

        // Add the list item with prefix
        $full_text = $prefix . $text_content;
        $pdf->Cell(0, 5, $full_text, 0, 1, 'L');

        // Process nested lists after this item
        foreach ($nested_lists as $nested_list) {
            process_list($nested_list, $pdf, $depth + 1, $nested_list->nodeName === 'ol');
        }
    }
    $pdf->Ln(3);
}

// Helper function to process image tags using regex
function process_image_tag($img_tag, &$pdf) {
    // Extract src and alt attributes
    $src = '';
    $alt = '';

    if (preg_match('/src=["\']([^"\']*)["\']/', $img_tag, $matches)) {
        $src = $matches[1];
    }

    if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $matches)) {
        $alt = $matches[1];
    }

    if (empty($src)) {
        return "\n[Image - no source]\n";
    }

    // Try multiple possible paths
    $possible_paths = [];

    if (str_starts_with($src, 'http')) {
        // HTTP URL - skip for now but note it
        return "\n[External Image: " . basename($src) . "]\n";
    } elseif (str_starts_with($src, '/')) {
        // Absolute path from web root
        $possible_paths[] = __DIR__ . $src;
        $possible_paths[] = __DIR__ . '/uploads/images/' . basename($src);
    } else {
        // Relative path
        $possible_paths[] = __DIR__ . '/' . $src;
        $possible_paths[] = __DIR__ . '/uploads/images/' . basename($src);
        $possible_paths[] = __DIR__ . '/uploads/files/' . basename($src);
    }

    $found_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $found_path = $path;
            break;
        }
    }

    if ($found_path) {
        try {
            $image_info = getimagesize($found_path);
            if ($image_info !== false) {
                // Check if we need a new page for the image
                $current_y = $pdf->GetY();
                $page_height = $pdf->getPageHeight();
                $margin_bottom = $pdf->getBreakMargin();
                $left_margin = $pdf->getMargins()['left'];
                $right_margin = $pdf->getMargins()['right'];

                // Reserve space for image + caption + spacing
                $space_needed = 100; // mm for image + caption + spacing
                if ($current_y + $space_needed > $page_height - $margin_bottom) {
                    $pdf->AddPage();
                    $current_y = $pdf->GetY();
                }

                // Calculate available width
                $available_width = $pdf->getPageWidth() - $left_margin - $right_margin;
                $max_width = min(140, $available_width - 40); // mm, leave 20mm margin each side
                $max_height = 90;  // mm

                // Convert image dimensions
                $img_width_px = $image_info[0];
                $img_height_px = $image_info[1];

                // Convert pixels to mm (assuming 96 DPI: 1px = 0.264583mm)
                $img_width_mm = $img_width_px * 0.264583;
                $img_height_mm = $img_height_px * 0.264583;

                // Calculate scaling to fit within constraints
                $scale = min($max_width / $img_width_mm, $max_height / $img_height_mm);
                $final_width = $img_width_mm * $scale;
                $final_height = $img_height_mm * $scale;

                // Calculate center position
                $x_pos = $left_margin + ($available_width - $final_width) / 2;

                // Move to next line before image
                $pdf->Ln(3);

                // Add image to PDF centered
                $pdf->Image($found_path, $x_pos, '', $final_width, $final_height, '', '', '', true, 300, '', false, false, 0, true, false);

                // Add caption if alt text exists
                if (!empty($alt)) {
                    $pdf->Ln(2); // Small space between image and caption
                    $pdf->SetFont('helvetica', 'I', 9);
                    $pdf->SetTextColor(100, 100, 100);
                    $pdf->Cell($available_width, 5, $alt, 0, 1, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 11);
                }

                $pdf->Ln(5); // Space after image and caption
                return ''; // Success, no text needed
            }
        } catch (Exception $e) {
            error_log("PDF Image Error: " . $e->getMessage() . " Path: " . $found_path);
        }
    }

    // Fallback - add placeholder text with debugging info
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $placeholder = !empty($alt) ? '[Image: ' . $alt . ']' : '[Image]';
    $pdf->MultiCell(0, 5, $placeholder, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(200, 100, 100);
    $debug_text = 'Original src: ' . $src;
    $pdf->MultiCell(0, 3, $debug_text, 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Ln(2);
    return '';
}

// Helper function to process list text with proper wrapping
function process_list_text($list_content, $ordered, &$pdf, $text_width) {
    $items = [];
    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $list_content, $matches);

    $pdf->Ln(3); // Add space before list
    $number = 1;

    foreach ($matches[1] as $item_content) {
        // Clean up the item content
        $item_text = strip_tags($item_content);
        $item_text = preg_replace('/\s+/', ' ', $item_text);
        $item_text = trim($item_text);

        if (!empty($item_text)) {
            $indent = 15; // Indentation for list items
            $prefix = $ordered ? $number . '. ' : '* ';
            $full_text = $prefix . $item_text;

            $pdf->SetFont('helvetica', '', 11);

            // Create a MultiCell with proper indentation
            $pdf->MultiCell($text_width, 5, $full_text, 0, 'L', false, 1, $indent);

            if ($ordered) $number++;
        }
    }

    $pdf->Ln(2); // Add space after list
    return '';
}

// Helper function to process tables
function process_table($table_node, &$pdf) {
    $pdf->Ln(5);
    $rows = $table_node->getElementsByTagName('tr');

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length === 0) {
            $cells = $row->getElementsByTagName('th');
        }

        $row_text = '';
        foreach ($cells as $cell) {
            $row_text .= trim($cell->textContent) . ' | ';
        }

        $pdf->Cell(0, 5, rtrim($row_text, ' | '), 0, 1, 'L');
    }
    $pdf->Ln(5);
}

// Simplified helper function for backward compatibility
function html_to_text($html) {
    global $pdf;
    $temp_pdf = $pdf;
    ob_start();
    render_html_to_pdf($html, $temp_pdf);
    $output = ob_get_clean();
    return strip_tags($output);
}

// Helper function to process unordered lists with proper indentation
function process_unordered_list($list_content, $indent_level) {
    $result = '';
    $indent = str_repeat('    ', $indent_level);

    // Process list items
    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $list_content, $items, PREG_SET_ORDER);

    foreach ($items as $item) {
        $item_content = $item[1];

        // Handle nested lists within this item
        $item_content = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) use ($indent_level) {
            return process_unordered_list($matches[1], $indent_level + 1);
        }, $item_content);

        $item_content = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) use ($indent_level) {
            return process_ordered_list($matches[1], $indent_level + 1);
        }, $item_content);

        // Clean up the item content
        $item_content = strip_tags($item_content);
        $item_content = preg_replace('/\s+/', ' ', $item_content);
        $item_content = trim($item_content);

        $result .= $indent . '• ' . $item_content . "\n";
    }

    return $result;
}

// Helper function to process ordered lists with proper numbering
function process_ordered_list($list_content, $start_number) {
    $result = '';

    // Process list items
    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $list_content, $items, PREG_SET_ORDER);

    foreach ($items as $index => $item) {
        $item_content = $item[1];
        $current_number = $start_number + $index;
        $indent = str_repeat('    ', 0);

        // Handle nested lists within this item
        $item_content = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) use ($current_number) {
            return "\n" . process_unordered_list($matches[1], 1);
        }, $item_content);

        $item_content = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
            return process_ordered_list($matches[1], 1);
        }, $item_content);

        // Clean up the item content
        $item_content = strip_tags($item_content);
        $item_content = preg_replace('/\s+/', ' ', $item_content);
        $item_content = trim($item_content);

        $result .= $indent . $current_number . '. ' . $item_content . "\n";
    }

    return $result;
}

// Helper function to process images for PDF
function process_image_for_pdf($image_src, $alt_text, &$pdf) {
    // Handle both absolute and relative URLs
    $image_path = $image_src;

    // Convert relative URLs to absolute file paths
    if (!str_starts_with($image_src, 'http') && !str_starts_with($image_src, '/')) {
        $image_path = __DIR__ . '/' . $image_src;
    } elseif (str_starts_with($image_src, '/')) {
        $image_path = __DIR__ . $image_src;
    }

    // Check if image file exists and is readable
    if (file_exists($image_path) && is_readable($image_path)) {
        try {
            // Get image dimensions to check if it's a valid image
            $image_info = getimagesize($image_path);
            if ($image_info !== false) {
                $current_y = $pdf->GetY();
                $page_height = $pdf->getPageHeight();
                $margin_bottom = $pdf->getBreakMargin();

                // Check if we need a new page for the image
                $max_height = 100; // Max image height in mm
                if ($current_y + $max_height > $page_height - $margin_bottom) {
                    $pdf->AddPage();
                }

                // Calculate image size (max width 150mm, max height 100mm)
                $max_width = 150;
                $max_height = 100;
                $img_width = $image_info[0];
                $img_height = $image_info[1];

                // Calculate scaling
                $scale_w = $max_width / $img_width;
                $scale_h = $max_height / $img_height;
                $scale = min($scale_w, $scale_h);

                $final_width = $img_width * $scale * 0.264583; // Convert pixels to mm (96 DPI)
                $final_height = $img_height * $scale * 0.264583;

                // Add the image to PDF
                $pdf->Image($image_path, '', '', $final_width, $final_height, '', '', '', true, 300, '', false, false, 0, true);

                // Add caption if alt text exists
                if (!empty($alt_text)) {
                    $pdf->SetFont('helvetica', 'I', 9);
                    $pdf->SetTextColor(100, 100, 100);
                    $pdf->Cell(0, 5, $alt_text, 0, 1, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }

                // Add some space after image
                $pdf->Ln(5);

                return '[Image included above]';
            }
        } catch (Exception $e) {
            // If there's an error with the image, return fallback text
            error_log("PDF Image Error: " . $e->getMessage());
        }
    }

    // Fallback if image can't be processed
    if (!empty($alt_text)) {
        return '[Image: ' . $alt_text . ']';
    }
    return '[Image]';
}

// Create PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Work Knowledge Base');
$pdf->SetAuthor('Work Knowledge Base');
$pdf->SetTitle($post['title']);
$pdf->SetSubject('Knowledge Base Export');

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Header
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 10, $post['title'], 0, 1, 'L');
$pdf->Ln(2);

// Breadcrumb
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$breadcrumb = $post['category_name'] . ' > ' . $post['subcategory_name'];
$pdf->Cell(0, 6, $breadcrumb, 0, 1, 'L');
$pdf->Ln(2);

// Posted date
$pdf->Cell(0, 6, 'Posted: ' . format_timestamp_pdf($post['created_at']), 0, 1, 'L');
$pdf->Ln(4);

// Separator line
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// Post content
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(0, 0, 0);
render_html_to_pdf($post['content'], $pdf);
$pdf->Ln(4);

// Attachments section
if (!empty($post_files)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Attachments:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    foreach ($post_files as $filename) {
        $pdf->Cell(0, 5, '- ' . $filename, 0, 1, 'L');
    }
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, '(See online version for file downloads)', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(4);
}

// Replies section
if (!empty($replies)) {
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Updates (' . count($replies) . ')', 0, 1, 'L');
    $pdf->Ln(2);

    foreach ($replies as $index => $reply) {
        // Fetch files for reply
        $stmt = $pdo->prepare("SELECT original_filename FROM files WHERE reply_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$reply['id']]);
        $reply_files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Reply number and timestamp
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Update #' . ($index + 1) . ' - ' . format_timestamp_pdf($reply['created_at']), 0, 1, 'L');

        // Edited indicator
        if ($reply['edited'] == 1) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Cell(0, 4, 'edited', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        // Reply content
        $pdf->SetFont('helvetica', '', 10);
        $reply_content = $reply['content'];
        render_html_to_pdf($reply_content, $pdf);

        // Reply attachments
        if (!empty($reply_files)) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 4, 'Attachments: ' . implode(', ', $reply_files), 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(4);
    }
}

// Footer
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 10, 'Exported from Work Knowledge Base on ' . date('M j, Y'), 0, 0, 'C');

// Generate filename
$title_slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($post['title']));
$title_slug = substr($title_slug, 0, 50); // Limit length
$filename = 'post_' . $post_id . '_' . $title_slug . '_' . date('Y-m-d') . '.pdf';

// Output PDF
$pdf->Output($filename, 'D'); // 'D' = force download
exit;
?>
