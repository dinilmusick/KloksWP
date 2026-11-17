<?php
declare(strict_types=1);

namespace KloksWP\Backend;

final class WorkspaceService {
    private function requireCapability(string $capability, ?int $postId = null): void {
        if (!function_exists('current_user_can') ||
            !current_user_can($capability, ...($postId === null ? [] : [$postId]))) {
            throw new \RuntimeException('WordPress workspace write permission denied.');
        }
    }

    public function create(string $name): int {
        if (trim($name) === '') throw new \InvalidArgumentException('Workspace name is required.');
        $this->requireCapability('edit_pages');
        $post = wp_insert_post([
            'post_type' => 'page',
            'post_title' => $name,
            'post_status' => 'draft',
        ], true);
        if (is_wp_error($post)) throw new \RuntimeException($post->get_error_message());
        if (!is_int($post) || $post <= 0) throw new \RuntimeException('WordPress workspace creation failed.');
        return $post;
    }

    public function update(int $id, array $data): int {
        if ($id <= 0) throw new \InvalidArgumentException('Valid workspace ID required.');
        $this->requireCapability('edit_post', $id);
        // A caller-supplied ID cannot redirect an authorized update.
        $data['ID'] = $id;
        $result = wp_update_post($data, true);
        if (is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
        if (!is_int($result) || $result <= 0) throw new \RuntimeException('WordPress workspace update failed.');
        return $result;
    }
}
