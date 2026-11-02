<?php
/**
 * Plugin Name: Student average calculator 
 * Description: student grade average calculator
 * Version: 1.0.0
 * Author: Jesson
 * Text Domain: https://jessonpaloma.com
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Student_Grade_Manager {
    const POST_TYPE = 'sgm_student';
    const NONCE_ACTION = 'sgm_save_student';
    const NONCE_NAME = 'sgm_nonce';

    private static $instance = null;
    private $subjects = array('math', 'science', 'history', 'music', 'pe');

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_filter('post_updated_messages', array($this, 'updated_messages'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_admin_meta'), 10, 2);
        add_shortcode('student_grade_form', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_sgm_save_student', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_sgm_save_student', array($this, 'handle_form_submission'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_admin_column'), 10, 2);
    }

    public function register_post_type() {
        $labels = array(
            'name' => __('Students', 'student-grade-manager'),
            'singular_name' => __('Student', 'student-grade-manager'),
            'add_new_item' => __('Add New Student', 'student-grade-manager'),
            'edit_item' => __('Edit Student', 'student-grade-manager'),
            'new_item' => __('New Student', 'student-grade-manager'),
            'view_item' => __('View Student', 'student-grade-manager'),
            'search_items' => __('Search Students', 'student-grade-manager'),
            'not_found' => __('No students found.', 'student-grade-manager'),
            'menu_name' => __('Students', 'student-grade-manager'),
        );

        register_post_type(self::POST_TYPE, array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'supports' => false,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'has_archive' => false,
        ));
    }

    public function add_meta_box() {
        remove_meta_box('submitdiv', self::POST_TYPE, 'side');
        add_meta_box(
            'sgm_student_grades',
            __('Student Grades', 'student-grade-manager'),
            array($this, 'render_admin_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_admin_meta_box($post) {
        wp_nonce_field('sgm_admin_save', 'sgm_admin_nonce');
        $name = get_post_meta($post->ID, '_sgm_name', true);
        echo '<p><label for="sgm_admin_name"><strong>' . esc_html__('Name', 'student-grade-manager') . '</strong></label><br>';
        echo '<input type="text" id="sgm_admin_name" name="sgm_name" class="widefat" value="' . esc_attr($name) . '"></p>';

        foreach ($this->subjects as $subject) {
            $value = get_post_meta($post->ID, '_sgm_' . $subject, true);
            printf(
                '<p><label for="sgm_admin_%1$s"><strong>%2$s</strong></label><br><input type="text" inputmode="decimal" maxlength="6" autocomplete="off" id="sgm_admin_%1$s" name="sgm_%1$s" class="widefat" value="%3$s"></p>',
                esc_attr($subject), esc_html($this->subject_label($subject)), esc_attr($value)
            );
        }
        $average = get_post_meta($post->ID, '_sgm_average', true);
        echo '<p><label><strong>' . esc_html__('Average', 'student-grade-manager') . '</strong></label><br>';
        echo '<input type="text" id="sgm_admin_average" class="widefat" value="' . esc_attr($average) . '" readonly></p>';
        echo '<p class="sgm-admin-actions">';
        echo '<button type="button" class="sgm-admin-button" id="sgm-admin-get-average">' . esc_html__('get average', 'student-grade-manager') . '</button>';
        echo '<button type="submit" class="sgm-admin-button" id="sgm-admin-save" name="publish" value="publish">' . esc_html__('save to database', 'student-grade-manager') . '</button>';
        echo '</p>';
        echo '<p class="sgm-admin-message" id="sgm-admin-message" role="status" aria-live="polite"></p>';
    }

    public function updated_messages($messages) {
        $saved = __('Student saved.', 'student-grade-manager');
        $messages[self::POST_TYPE] = array_fill(1, 10, $saved);
        $messages[self::POST_TYPE][0] = '';
        return $messages;
    }

    public function save_admin_meta($post_id, $post) {
        if (!isset($_POST['sgm_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sgm_admin_nonce'])), 'sgm_admin_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $name = isset($_POST['sgm_name']) ? sanitize_text_field(wp_unslash($_POST['sgm_name'])) : '';
        update_post_meta($post_id, '_sgm_name', $name);
        $grades = array();
        foreach ($this->subjects as $subject) {
            $raw = isset($_POST['sgm_' . $subject]) ? wp_unslash($_POST['sgm_' . $subject]) : '';
            $grade = ($raw === '') ? '' : $this->normalize_grade($raw);
            if ($grade === false) $grade = '';
            update_post_meta($post_id, '_sgm_' . $subject, $grade);
            if ($grade !== '') $grades[] = (float) $grade;
        }
        $average = count($grades) === count($this->subjects) ? round(array_sum($grades) / count($grades), 2) : '';
        update_post_meta($post_id, '_sgm_average', $average);
        if ($name !== '' && $post->post_title !== $name) {
            remove_action('save_post_' . self::POST_TYPE, array($this, 'save_admin_meta'), 10);
            wp_update_post(array('ID' => $post_id, 'post_title' => $name));
            add_action('save_post_' . self::POST_TYPE, array($this, 'save_admin_meta'), 10, 2);
        }
    }

    public function register_assets() {
        wp_register_style('sgm-form', plugins_url('assets/css/student-form.css', __FILE__), array(), '1.0.2');
        wp_register_script('sgm-form', plugins_url('assets/js/student-form.js', __FILE__), array(), '1.0.4', true);
        wp_localize_script('sgm-form', 'SGM_CONFIG', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'messages' => array(
                'invalid' => __('Please enter a name and all five grades (0–100, up to 2 decimal places).', 'student-grade-manager'),
                'error' => __('Unable to save student. Please try again.', 'student-grade-manager'),
                'success' => __('Student saved successfully.', 'student-grade-manager'),
            ),
        ));
    }

    public function enqueue_admin_assets($hook) {
        if ($hook === 'edit.php') {
            $screen = get_current_screen();
            if ($screen && self::POST_TYPE === $screen->post_type) {
                wp_enqueue_style('sgm-admin', plugins_url('assets/css/student-admin.css', __FILE__), array(), '1.0.3');
                wp_enqueue_script('sgm-list', plugins_url('assets/js/student-list.js', __FILE__), array(), '1.0.4', true);
            }
        }
        if (in_array($hook, array('post.php', 'post-new.php'), true)) {
            $screen = get_current_screen();
            if ($screen && self::POST_TYPE === $screen->post_type) {
                wp_enqueue_style('sgm-admin', plugins_url('assets/css/student-admin.css', __FILE__), array(), '1.0.3');
                wp_enqueue_script('sgm-admin', plugins_url('assets/js/student-admin.js', __FILE__), array(), '1.0.4', true);
                wp_localize_script('sgm-admin', 'SGM_ADMIN', array(
                    'invalid' => __('Please enter a name and all five grades (0–100, up to 2 decimal places).', 'student-grade-manager'),
                ));
            }
        }
    }

    public function render_form() {
        wp_enqueue_style('sgm-form');
        wp_enqueue_script('sgm-form');
        ob_start(); ?>
        <form class="sgm-form" id="sgm-student-form" novalidate>
            <div class="sgm-fields">
                <div class="sgm-field"><label for="sgm-name">Name :</label><input id="sgm-name" name="name" type="text" autocomplete="name" required></div>
                <?php foreach ($this->subjects as $subject) : ?>
                    <div class="sgm-field">
                        <label for="sgm-<?php echo esc_attr($subject); ?>"><?php echo esc_html($this->subject_label($subject)); ?> :</label>
                        <input id="sgm-<?php echo esc_attr($subject); ?>" name="<?php echo esc_attr($subject); ?>" type="text" inputmode="decimal" maxlength="6" autocomplete="off" required>
                    </div>
                <?php endforeach; ?>
                <div class="sgm-field sgm-average-field"><label for="sgm-average">Average :</label><input id="sgm-average" name="average" type="text" readonly aria-live="polite"></div>
            </div>
            <div class="sgm-actions">
                <button type="button" class="sgm-button" id="sgm-get-average">get average</button>
                <button type="submit" class="sgm-button" id="sgm-save">save to database</button>
            </div>
            <p class="sgm-message" id="sgm-message" role="status" aria-live="polite"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_form_submission() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if ($name === '') wp_send_json_error(array('message' => __('Please enter a student name.', 'student-grade-manager')), 400);

        $grades = array();
        foreach ($this->subjects as $subject) {
            if (!isset($_POST[$subject]) || $_POST[$subject] === '') {
                wp_send_json_error(array('message' => __('Please enter all five grades.', 'student-grade-manager')), 400);
            }
            $grade = $this->normalize_grade(wp_unslash($_POST[$subject]));
            if ($grade === false) wp_send_json_error(array('message' => __('Grades must be numbers from 0 to 100 with up to 2 decimal places.', 'student-grade-manager')), 400);
            $grades[$subject] = $grade;
        }
        $average = round(array_sum(array_map('floatval', $grades)) / count($grades), 2);

        $post_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $name,
        ), true);
        if (is_wp_error($post_id)) wp_send_json_error(array('message' => __('Could not save the student record.', 'student-grade-manager')), 500);

        update_post_meta($post_id, '_sgm_name', $name);
        foreach ($grades as $subject => $grade) update_post_meta($post_id, '_sgm_' . $subject, $grade);
        update_post_meta($post_id, '_sgm_average', $average);

        wp_send_json_success(array('message' => __('Student saved successfully.', 'student-grade-manager'), 'average' => number_format($average, 2), 'studentId' => $post_id));
    }

    public function admin_columns($columns) {
        $result = array();
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['sgm_average'] = __('Average', 'student-grade-manager');
            }
        }
        $result['sgm_actions'] = __('Actions', 'student-grade-manager');
        return $result;
    }

    public function render_admin_column($column, $post_id) {
        if ($column === 'sgm_average') {
            $average = get_post_meta($post_id, '_sgm_average', true);
            echo $average === '' ? '&mdash;' : esc_html(number_format((float) $average, 2));
        }
        if ($column === 'sgm_actions') {
            printf(
                '<button type="button" class="sgm-view-button" data-sgm-student="%s">%s</button>',
                esc_attr(wp_json_encode($this->student_details($post_id))),
                esc_html__('View', 'student-grade-manager')
            );
        }
    }

    private function student_details($post_id) {
        $name = get_post_meta($post_id, '_sgm_name', true);
        $average = get_post_meta($post_id, '_sgm_average', true);
        $grades = array();
        foreach ($this->subjects as $subject) {
            $grades[] = array(
                'label' => $this->subject_label($subject),
                'value' => get_post_meta($post_id, '_sgm_' . $subject, true),
            );
        }
        return array(
            'name' => $name !== '' ? $name : get_the_title($post_id),
            'grades' => $grades,
            'average' => $average === '' ? '' : number_format((float) $average, 2),
        );
    }

    private function normalize_grade($raw) {
        if (!is_numeric($raw) || !preg_match('/^\d*\.?\d{0,2}$/', (string) $raw) || preg_match('/^0*100\./', (string) $raw)) return false;
        $grade = (float) $raw;
        if ($grade < 0 || $grade > 100) return false;
        return round($grade, 2);
    }

    private function subject_label($subject) {
        $labels = array('math' => 'Math', 'science' => 'Science', 'history' => 'History', 'music' => 'Music', 'pe' => 'P.E.');
        return isset($labels[$subject]) ? $labels[$subject] : ucfirst($subject);
    }
}

Student_Grade_Manager::instance();
