<?php
/*
Plugin Name: contentScale
Plugin URI: google.com
Description: Plugin para contar la cantidad de veces que una palabra se repite en todas las páginas.
Version: 0.0.1
Author: m1gue
Author URI: google.com
License: GPL2
*/

add_shortcode('word_counter', 'word_counter_shortcode');
function word_counter_shortcode()
{
    ob_start();
    ?>
    <div style="text-align: center;">
        <form method="post" action="">
            <input type="text" name="search_word" placeholder="Ingresa una palabra">
            <input type="submit" value="Buscar">
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Realiza la búsqueda de la palabra y muestra los resultados
add_action('init', 'word_counter_search');
function word_counter_search()
{
    $word_instances = array();
    $search_word = '';

    if (isset($_POST['search_word']) && !empty($_POST['search_word'])) {
        $search_word = sanitize_text_field($_POST['search_word']);
        $word_count = 0;

        $args = array(
            'post_type' => 'page',
            // indica que no hay límite
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $content = get_the_content();
                $lowercase_content = strtolower($content);
                $matches = array();
                $pattern = '/\b(' . preg_quote($search_word, '/') . ')\b/i';
                preg_match_all($pattern, $lowercase_content, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as $match) {
                    $start_position = $match[1];
                    $end_position = $start_position + strlen($match[0]);
                    $context_start = max(0, $start_position - 15);
                    $context_end = min(strlen($content), $end_position + 15);
                    $context = substr($content, $context_start, $context_end - $context_start);
                    $word_instances[] = array(
                        'context' => $context,
                        'permalink' => get_permalink(),
                        'selected' => false,
                        'post_id' => get_the_ID(),
                    );
                    $word_count++;
                }
            }
        }

        wp_reset_postdata();
    }

    if (!empty($word_instances)) {
        echo '<div style="text-align: center;">';
        echo 'La palabra "' . $search_word . '" se repite ' . $word_count . ' veces en todas las páginas.<br><br>';
        echo 'Resultados para la palabra "' . $search_word . '":<br><br>';
        echo '<form method="post" action="">';
        echo '<table style="margin: 0 auto; width: 80%; border-collapse: collapse;">';
        echo '<tr><th style="text-align: left;">Contexto</th><th style="text-align: left;">Enlace</th><th style="text-align: left;">Selecciona para Reemplazar</th></tr>';
        foreach ($word_instances as $key => $instance) {
            echo '<tr>';
            echo '<td style="vertical-align: top;">' . $instance['context'] . '</td>';
            echo '<td style="vertical-align: top;"><a href="' . $instance['permalink'] . '">' . $instance['permalink'] . '</a></td>';
            echo '<td style="vertical-align: top;"><input type="checkbox" name="selected[]" value="' . $key . '"></td>';
            echo '</tr>';
            echo '<tr><td colspan="3" style="height: 10px;"></td></tr>'; // Línea separadora
        }
        echo '</table>';
        echo '<br>';
        echo 'Accion: <input type="text" name="replacement" placeholder="Ingresa el nuevo valor">';
        echo 'Condicion: <input type="text" name="condition" placeholder="ingresa la condicion">';
        echo '<br><br>';
        echo '<input type="submit" name="replace_submit" value="Reemplazar">';
        echo '</form>';
        echo '</div>';

        // Lógica para realizar el reemplazo del contenido de las páginas seleccionadas
        if (isset($_POST['replace_submit'])) {
            $replacement = isset($_POST['replacement']) ? sanitize_text_field($_POST['replacement']) : '';
            $replaced_instances = array(); // Array para almacenar los elementos reemplazados

            foreach ($word_instances as $key => $instance) {
                $post_id = $instance['post_id'];
                $content = get_post_field('post_content', $post_id);
                $updated_content = str_replace($search_word, $replacement, $content);

                // Aplicar filtro para asegurarse de obtener el contenido sin modificaciones
                $updated_content = apply_filters('content_save_pre', $updated_content);

                $post_data = array(
                    'ID' => $post_id,
                    'post_content' => $updated_content,
                );

                // Actualizar el contenido de la página
                wp_update_post($post_data);

                // Almacenar los elementos reemplazados
                $replaced_instances[] = $instance;
            }

            // Mostrar la tabla con los elementos reemplazados
            echo '<div style="text-align: center;">';
            echo 'Elementos Reemplazados:<br><br>';
            echo '<table style="margin: 0 auto; width: 80%; border-collapse: collapse;">';
            echo '<tr><th style="text-align: left;">Contexto</th><th style="text-align: left;">Enlace</th></tr>';
            foreach ($replaced_instances as $instance) {
                echo '<tr>';
                echo '<td style="vertical-align: top;">' . $instance['context'] . '</td>';
                echo '<td style="vertical-align: top;"><a href="' . $instance['permalink'] . '">' . $instance['permalink'] . '</a></td>';
                echo '</tr>';
                echo '<tr><td colspan="2" style="height: 10px;"></td></tr>'; // Línea separadora
            }
            echo '</table>';
            echo '</div>';
        }
    } else {
        echo '<div style="text-align: center;">';
        echo 'No se encontraron resultados para la palabra "' . $search_word . '".';
        echo '</div>';
    }
}

// Agrega el enlace al buscador en el menú
add_action('admin_menu', 'word_counter_add_menu_link');
function word_counter_add_menu_link()
{
    add_menu_page(
        'SmoothStitute',
        'SmoothStitute',
        'manage_options',
        'word-counter',
        'word_counter_page',
        'dashicons-search',
        6
    );
}

// Función para mostrar la página del buscador
function word_counter_page()
{
    echo '<div style="text-align: center;">';
    echo '<h1>Buscador SmoothStitute</h1>';
    echo do_shortcode('[word_counter]');
    echo '</div>';
}

// Crea la página "Buscador" al activar el plugin
register_activation_hook(__FILE__, 'word_counter_create_page');
function word_counter_create_page()
{
    $page_title = 'Buscador';
    $page_content = '[word_counter]';

    // Verifica si la página ya existe
    $page_query = new WP_Query(
        array(
            'post_type' => 'page',
            'post_status' => 'any',
            'title' => $page_title,
            'posts_per_page' => 1
        )
    );

    if (!$page_query->have_posts()) {
        // Crea la página
        $page_args = array(
            'post_title' => $page_title,
            'post_content' => $page_content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'buscador'
        );

        wp_insert_post($page_args);
    }

    // Restablece la consulta original de WordPress
    wp_reset_postdata();
}