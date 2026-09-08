<?php

declare(strict_types=1);

namespace VotingSystem\Controllers;

use PDO;
use PDOException;
use VotingSystem\Core\Database;
use VotingSystem\Core\HttpException;
use VotingSystem\Core\Request;
use VotingSystem\Core\Response;

final class VoteController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(): void
    {
        $stmt = $this->db->query(
            'SELECT votes.id,
                    votes.voter_id,
                    voters.name AS voter_name,
                    votes.candidate_id,
                    candidates.name AS candidate_name,
                    votes.created_at
             FROM votes
             INNER JOIN voters ON voters.id = votes.voter_id
             INNER JOIN candidates ON candidates.id = votes.candidate_id
             ORDER BY votes.id DESC'
        );

        Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    public function store(Request $request): void
    {
        $voterId = (int) $request->input('voter_id', 0);
        $candidateId = (int) $request->input('candidate_id', 0);
        $errors = [];

        if ($voterId <= 0) {
            $errors['voter_id'][] = 'The voter_id field must be a valid positive integer.';
        }

        if ($candidateId <= 0) {
            $errors['candidate_id'][] = 'The candidate_id field must be a valid positive integer.';
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed.', $errors);
        }

        $this->db->beginTransaction();

        try {
            $voter = $this->lockVoter($voterId);
            $candidate = $this->lockCandidate($candidateId);

            if (!$voter) {
                throw new HttpException(404, 'Voter not found.');
            }

            if (!$candidate) {
                throw new HttpException(404, 'Candidate not found.');
            }

            if ((bool) $voter['has_voted']) {
                throw new HttpException(409, 'This voter has already voted.');
            }

            $voteStmt = $this->db->prepare(
                'INSERT INTO votes (voter_id, candidate_id) VALUES (:voter_id, :candidate_id)'
            );
            $voteStmt->execute(['voter_id' => $voterId, 'candidate_id' => $candidateId]);
            $voteId = (int) $this->db->lastInsertId();

            $this->db->prepare('UPDATE voters SET has_voted = TRUE WHERE id = :id')
                ->execute(['id' => $voterId]);

            $this->db->prepare('UPDATE candidates SET votes = votes + 1 WHERE id = :id')
                ->execute(['id' => $candidateId]);

            $this->db->commit();

            Response::json([
                'success' => true,
                'message' => 'Vote registered successfully.',
                'data' => $this->findVote($voteId),
            ], 201);
        } catch (HttpException $exception) {
            $this->db->rollBack();
            throw $exception;
        } catch (PDOException $exception) {
            $this->db->rollBack();

            if ($exception->getCode() === '23000') {
                throw new HttpException(409, 'This voter has already voted.');
            }

            throw $exception;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function statistics(): void
    {
        $totalVotes = (int) $this->db->query('SELECT COUNT(*) FROM votes')->fetchColumn();
        $votersThatVoted = (int) $this->db->query('SELECT COUNT(*) FROM voters WHERE has_voted = TRUE')->fetchColumn();

        $stmt = $this->db->query(
            'SELECT id AS candidate_id, name, party, votes
             FROM candidates
             ORDER BY votes DESC, name ASC'
        );

        $candidates = array_map(
            static fn (array $candidate) => [
                'candidate_id' => (int) $candidate['candidate_id'],
                'name' => $candidate['name'],
                'party' => $candidate['party'],
                'votes' => (int) $candidate['votes'],
                'percentage' => $totalVotes === 0
                    ? 0.0
                    : round(((int) $candidate['votes'] / $totalVotes) * 100, 2),
            ],
            $stmt->fetchAll()
        );

        Response::json([
            'success' => true,
            'data' => [
                'total_votes' => $totalVotes,
                'total_voters_that_voted' => $votersThatVoted,
                'results' => $candidates,
            ],
        ]);
    }

    private function lockVoter(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT id, has_voted FROM voters WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    private function lockCandidate(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT id FROM candidates WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    private function findVote(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT votes.id,
                    votes.voter_id,
                    voters.name AS voter_name,
                    votes.candidate_id,
                    candidates.name AS candidate_name,
                    votes.created_at
             FROM votes
             INNER JOIN voters ON voters.id = votes.voter_id
             INNER JOIN candidates ON candidates.id = votes.candidate_id
             WHERE votes.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: [];
    }
}
