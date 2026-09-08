<?php

declare(strict_types=1);

namespace VotingSystem\Controllers;

use PDO;
use PDOException;
use VotingSystem\Core\Database;
use VotingSystem\Core\HttpException;
use VotingSystem\Core\Request;
use VotingSystem\Core\Response;

final class CandidateController
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
            $where = 'WHERE name LIKE :search OR party LIKE :search';
            $filters['search'] = "%{$search}%";
        }

        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM candidates {$where}");
        $totalStmt->execute($filters);

        $stmt = $this->db->prepare(
            "SELECT id, name, party, votes, created_at, updated_at
             FROM candidates {$where}
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
        $candidate = $this->find((int) $params['id']);
        Response::json(['success' => true, 'data' => $candidate]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name', ''));
        $party = $request->input('party');
        $party = $party === null ? null : trim((string) $party);
        $errors = [];

        if ($name === '') {
            $errors['name'][] = 'The name field is required.';
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed.', $errors);
        }

        $this->ensureNameIsNotVoter($name);

        try {
            $stmt = $this->db->prepare('INSERT INTO candidates (name, party) VALUES (:name, :party)');
            $stmt->execute(['name' => $name, 'party' => $party === '' ? null : $party]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException(409, 'A candidate with this name already exists.');
            }

            throw $exception;
        }

        Response::json([
            'success' => true,
            'message' => 'Candidate registered successfully.',
            'data' => $this->find((int) $this->db->lastInsertId()),
        ], 201);
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->find($id);

        try {
            $stmt = $this->db->prepare('DELETE FROM candidates WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException(409, 'This candidate already has votes and cannot be deleted.');
            }

            throw $exception;
        }

        Response::json(['success' => true, 'message' => 'Candidate deleted successfully.']);
    }

    private function find(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, party, votes, created_at, updated_at FROM candidates WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $candidate = $stmt->fetch();

        if (!$candidate) {
            throw new HttpException(404, 'Candidate not found.');
        }

        return $candidate;
    }

    private function ensureNameIsNotVoter(string $name): void
    {
        $stmt = $this->db->prepare('SELECT id FROM voters WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute(['name' => $name]);

        if ($stmt->fetch()) {
            throw new HttpException(409, 'This person is already registered as a voter.');
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
