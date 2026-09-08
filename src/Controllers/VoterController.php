<?php

declare(strict_types=1);

namespace VotingSystem\Controllers;

use PDO;
use PDOException;
use VotingSystem\Core\Database;
use VotingSystem\Core\HttpException;
use VotingSystem\Core\Request;
use VotingSystem\Core\Response;

final class VoterController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(Request $request): void
    {
        $pagination = $this->pagination($request);
        $search = trim((string) ($request->query['search'] ?? ''));
        $filters = [];
        $where = '';

        if ($search !== '') {
            $where = 'WHERE name LIKE :search OR email LIKE :search';
            $filters['search'] = "%{$search}%";
        }

        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM voters {$where}");
        $totalStmt->execute($filters);

        $stmt = $this->db->prepare(
            "SELECT id, name, email, has_voted, created_at, updated_at
             FROM voters {$where}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($filters as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        Response::json([
            'success' => true,
            'data' => $stmt->fetchAll(),
            'meta' => [
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total' => (int) $totalStmt->fetchColumn(),
            ],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $voter = $this->find((int) $params['id']);
        Response::json(['success' => true, 'data' => $voter]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $errors = [];

        if ($name === '') {
            $errors['name'][] = 'The name field is required.';
        }

        if ($email === '') {
            $errors['email'][] = 'The email field is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'The email field must be a valid email address.';
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed.', $errors);
        }

        $this->ensureNameIsNotCandidate($name);

        try {
            $stmt = $this->db->prepare('INSERT INTO voters (name, email) VALUES (:name, :email)');
            $stmt->execute(['name' => $name, 'email' => $email]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException(409, 'A voter with this email already exists.');
            }

            throw $exception;
        }

        Response::json([
            'success' => true,
            'message' => 'Voter registered successfully.',
            'data' => $this->find((int) $this->db->lastInsertId()),
        ], 201);
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->find($id);

        try {
            $stmt = $this->db->prepare('DELETE FROM voters WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException(409, 'This voter already has a vote and cannot be deleted.');
            }

            throw $exception;
        }

        Response::json(['success' => true, 'message' => 'Voter deleted successfully.']);
    }

    private function find(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, has_voted, created_at, updated_at FROM voters WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $voter = $stmt->fetch();

        if (!$voter) {
            throw new HttpException(404, 'Voter not found.');
        }

        return $voter;
    }

    private function ensureNameIsNotCandidate(string $name): void
    {
        $stmt = $this->db->prepare('SELECT id FROM candidates WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute(['name' => $name]);

        if ($stmt->fetch()) {
            throw new HttpException(409, 'This person is already registered as a candidate.');
        }
    }

    private function pagination(Request $request): array
    {
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($request->query['limit'] ?? 15)));

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }
}
