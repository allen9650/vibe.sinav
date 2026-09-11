<?php
/**
 * Certificate Management & Generation Engine
 * Marr Typing Competition System
 *
 * Handles official achievement & participation certificates,
 * unique numbering (MITC-CERT-YYYY-XXXXX), eligibility policies,
 * snapshot preservation, and bulk generation.
 */

class CertificateService {
    /**
     * Generates a unique, collision-free certificate number.
     * Format: MITC-CERT-YYYY-XXXXX (e.g., MITC-CERT-2026-00001)
     *
     * @return string
     */
    public static function generateCertificateNumber(): string {
        $year = date('Y');
        $prefix = "MITC-CERT-{$year}-";

        $maxSeq = Database::fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(certificate_number, LENGTH(?) + 1) AS UNSIGNED)) 
             FROM certificates 
             WHERE certificate_number LIKE ?",
            [$prefix, $prefix . '%']
        );

        $nextSeq = ($maxSeq ? (int)$maxSeq : 0) + 1;
        return sprintf('%s%05d', $prefix, $nextSeq);
    }

    /**
     * Supported professional certificate templates.
     */
    public static function getAvailableTemplates(): array {
        return [
            'classic' => [
                'name'        => 'Classic Professional',
                'description' => 'Elegant institutional certificate with ornamental borders & formal serif typography',
                'best_for'    => 'Participation / Appreciation',
                'icon'        => 'fa-award',
                'theme'       => 'gold-navy',
            ],
            'modern' => [
                'name'        => 'Modern Premium',
                'description' => 'Clean geometric layout with high-contrast typography & achievement badges',
                'best_for'    => 'Achievement / Qualified Candidates',
                'icon'        => 'fa-certificate',
                'theme'       => 'slate-sapphire',
            ],
            'academic' => [
                'name'        => 'Academic Excellence',
                'description' => 'Traditional academic honors diploma layout with ornate cornerpieces & official crest',
                'best_for'    => 'Academic Honors & High Performers',
                'icon'        => 'fa-graduation-cap',
                'theme'       => 'maroon-gold',
            ],
            'champion' => [
                'name'        => 'Typing Champion',
                'description' => 'Prestigious winner award design with dynamic 1st / 2nd / 3rd medal visuals',
                'best_for'    => 'Top 3 Winners & Competition Finalists',
                'icon'        => 'fa-trophy',
                'theme'       => 'gold-champion',
            ],
        ];
    }

    /**
     * Updates certificate template without modifying numbering or data.
     */
    public static function updateTemplate(int $certId, string $templateKey): bool {
        $templates = self::getAvailableTemplates();
        if (!isset($templates[$templateKey])) {
            $templateKey = 'classic';
        }

        return Database::update('certificates', [
            'template_key' => $templateKey
        ], 'id = ?', [$certId]) >= 0;
    }

    /**
     * Updates custom wording, signatories, display name, and honor titles for a certificate.
     */
    public static function updateCertificateCustomization(int $certId, array $data, ?int $updatedBy = null): bool {
        $updateFields = [];

        if (isset($data['template_key'])) {
            $templates = self::getAvailableTemplates();
            $updateFields['template_key'] = isset($templates[$data['template_key']]) ? $data['template_key'] : 'classic';
        }

        if (isset($data['certificate_type'])) {
            $validTypes = ['position', 'participation', 'merit'];
            if (in_array($data['certificate_type'], $validTypes)) {
                $updateFields['certificate_type'] = $data['certificate_type'];
            }
        }

        if (isset($data['position'])) {
            $updateFields['position'] = trim($data['position']);
        }

        if (isset($data['display_name'])) {
            $updateFields['display_name'] = trim($data['display_name']) !== '' ? trim($data['display_name']) : null;
        }

        if (isset($data['custom_statement'])) {
            $updateFields['custom_statement'] = trim($data['custom_statement']) !== '' ? trim($data['custom_statement']) : null;
        }

        if (isset($data['signatory_1_title'])) {
            $updateFields['signatory_1_title'] = trim($data['signatory_1_title']) !== '' ? trim($data['signatory_1_title']) : null;
        }

        if (isset($data['signatory_1_name'])) {
            $updateFields['signatory_1_name'] = trim($data['signatory_1_name']) !== '' ? trim($data['signatory_1_name']) : null;
        }

        if (isset($data['signatory_2_title'])) {
            $updateFields['signatory_2_title'] = trim($data['signatory_2_title']) !== '' ? trim($data['signatory_2_title']) : null;
        }

        if (isset($data['signatory_2_name'])) {
            $updateFields['signatory_2_name'] = trim($data['signatory_2_name']) !== '' ? trim($data['signatory_2_name']) : null;
        }

        if (empty($updateFields)) {
            return false;
        }

        $res = Database::update('certificates', $updateFields, 'id = ?', [$certId]);

        AuditLog::log('certificate_customized', 'certificates', 
            "Certificate #{$certId} customization updated.", 
            $updatedBy, 
            null, 
            $updateFields
        );

        return $res >= 0;
    }

    /**
     * Resets certificate customizations back to standard defaults.
     */
    public static function resetCertificateCustomization(int $certId, ?int $resetBy = null): bool {
        $res = Database::update('certificates', [
            'display_name'      => null,
            'custom_statement'  => null,
            'signatory_1_title' => null,
            'signatory_1_name'  => null,
            'signatory_2_title' => null,
            'signatory_2_name'  => null,
        ], 'id = ?', [$certId]);

        AuditLog::log('certificate_reset', 'certificates', "Certificate #{$certId} customizations reset to default.", $resetBy);

        return $res >= 0;
    }

    /**
     * Generates an individual certificate for a candidate.
     *
     * @param int $candidateId
     * @param int $competitionId
     * @param string $certType 'position' | 'participation'
     * @param int|null $issuedBy Admin user ID
     * @param string $templateKey 'classic' | 'modern' | 'academic' | 'champion'
     * @return array
     */
    public static function generateCertificate(
        int $candidateId, 
        int $competitionId, 
        string $certType = 'participation', 
        ?int $issuedBy = null,
        string $templateKey = 'classic'
    ): array {
        $comp = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
        if (!$comp) {
            throw new Exception("Competition not found.");
        }

        $cand = Database::fetch("SELECT * FROM candidates WHERE id = ? AND competition_id = ?", [$candidateId, $competitionId]);
        if (!$cand) {
            throw new Exception("Candidate not found for this competition.");
        }

        // Check if certificate already issued
        $existing = Database::fetch(
            "SELECT * FROM certificates WHERE candidate_id = ? AND competition_id = ? AND certificate_type = ?",
            [$candidateId, $competitionId, $certType]
        );
        if ($existing) {
            return [
                'status'             => 'already_exists',
                'certificate_id'     => $existing['id'],
                'certificate_number' => $existing['certificate_number'],
                'message'            => "Certificate already issued (#{$existing['certificate_number']}).",
            ];
        }

        // Fetch candidate's authoritative best completed test result
        $result = RankingService::getBestFinalizedResultForCandidate($candidateId, $competitionId);

        if (!$result) {
            throw new Exception("No completed test result found for candidate {$cand['full_name']}.");
        }

        // For position certificates, require competition finalization
        if ($certType === 'position') {
            if (empty($comp['results_finalized_at'])) {
                throw new Exception("Position certificates can only be generated after the competition has been finalized.");
            }
        }

        // Format position title
        $positionTitle = 'Participant';
        if (!empty($result['rank'])) {
            $rankNum = (int)$result['rank'];
            if ($rankNum === 1) $positionTitle = '1st Position';
            elseif ($rankNum === 2) $positionTitle = '2nd Position';
            elseif ($rankNum === 3) $positionTitle = '3rd Position';
            else $positionTitle = "Rank #{$rankNum}";
        }

        // Default template based on certificate type if classic wasn't explicitly changed
        if ($templateKey === 'classic' && $certType === 'position') {
            $templateKey = 'champion';
        }

        $templates = self::getAvailableTemplates();
        if (!isset($templates[$templateKey])) {
            $templateKey = 'classic';
        }

        $certNumber = self::generateCertificateNumber();

        $certId = Database::insert('certificates', [
            'certificate_number' => $certNumber,
            'competition_id'     => $competitionId,
            'candidate_id'       => $candidateId,
            'result_id'          => $result['id'],
            'certificate_type'   => $certType,
            'template_key'       => $templateKey,
            'position'           => $positionTitle,
            'net_wpm_snapshot'   => $result['net_wpm'],
            'accuracy_snapshot'  => $result['accuracy'],
            'score_snapshot'     => $result['score'],
            'issued_at'          => date('Y-m-d H:i:s'),
            'issued_by'          => $issuedBy,
            'status'             => 'valid',
        ]);

        AuditLog::log('certificate_generated', 'certificates', 
            "Certificate {$certNumber} ({$certType}, template: {$templateKey}) generated for candidate #{$candidateId} ('{$cand['full_name']}').", 
            $issuedBy, 
            null, 
            ['certificate_id' => $certId, 'certificate_number' => $certNumber, 'candidate_id' => $candidateId, 'template_key' => $templateKey]
        );

        return [
            'status'             => 'generated',
            'certificate_id'     => $certId,
            'certificate_number' => $certNumber,
            'message'            => "Certificate #{$certNumber} successfully generated.",
        ];
    }

    /**
     * Bulk generates certificates for all eligible candidates in a competition.
     *
     * @param int $competitionId
     * @param string $mode 'top_3' | 'top_10' | 'all_completed' | 'all_qualified' | 'custom'
     * @param array $customCriteria ['min_wpm' => 30, 'min_accuracy' => 90]
     * @param int|null $issuedBy
     * @param string $templateKey
     * @return array
     */
    public static function bulkGenerate(
        int $competitionId, 
        string $mode = 'all_completed', 
        array $customCriteria = [], 
        ?int $issuedBy = null,
        string $templateKey = 'classic'
    ): array {
        $comp = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
        if (!$comp) {
            throw new Exception("Competition not found.");
        }

        $rankedList = RankingService::getLeaderboard($competitionId, false);

        $eligible = [];
        foreach ($rankedList as $entry) {
            $rank = (int)$entry['rank'];
            $isQualified = ($entry['qualification_status'] === 'qualified');
            $netWpm = (float)$entry['net_wpm'];
            $accuracy = (float)$entry['accuracy'];

            $isEligible = false;
            $certType = 'participation';

            switch ($mode) {
                case 'top_3':
                    if ($rank <= 3) {
                        $isEligible = true;
                        $certType = 'position';
                    }
                    break;
                case 'top_10':
                    if ($rank <= 10) {
                        $isEligible = true;
                        $certType = ($rank <= 3) ? 'position' : 'participation';
                    }
                    break;
                case 'all_completed':
                    $isEligible = true;
                    $certType = ($rank <= 3 && !empty($comp['results_finalized_at'])) ? 'position' : 'participation';
                    break;
                case 'all_qualified':
                    if ($isQualified) {
                        $isEligible = true;
                        $certType = ($rank <= 3 && !empty($comp['results_finalized_at'])) ? 'position' : 'participation';
                    }
                    break;
                case 'custom':
                    $minWpm = (float)($customCriteria['min_wpm'] ?? 0);
                    $minAcc = (float)($customCriteria['min_accuracy'] ?? 0);
                    if ($netWpm >= $minWpm && $accuracy >= $minAcc) {
                        $isEligible = true;
                        $certType = ($rank <= 3 && !empty($comp['results_finalized_at'])) ? 'position' : 'participation';
                    }
                    break;
            }

            if ($isEligible) {
                $eligible[] = [
                    'candidate_id' => (int)$entry['candidate_id'],
                    'cert_type'    => $certType,
                ];
            }
        }

        $generatedCount = 0;
        $skippedCount = 0;

        Database::beginTransaction();
        try {
            foreach ($eligible as $item) {
                $res = self::generateCertificate($item['candidate_id'], $competitionId, $item['cert_type'], $issuedBy, $templateKey);
                if ($res['status'] === 'generated') {
                    $generatedCount++;
                } else {
                    $skippedCount++;
                }
            }
            Database::commit();

            AuditLog::log('bulk_certificates_generated', 'certificates', 
                "Bulk generated {$generatedCount} certificate(s) for competition #{$competitionId} (Mode: {$mode}).", 
                $issuedBy, 
                null, 
                ['competition_id' => $competitionId, 'generated' => $generatedCount, 'skipped' => $skippedCount]
            );

            return [
                'status'    => 'success',
                'eligible'  => count($eligible),
                'generated' => $generatedCount,
                'skipped'   => $skippedCount,
            ];
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Fetches complete certificate details with candidate & competition metadata.
     *
     * @param int $certId
     * @return array|null
     */
    public static function getCertificate(int $certId): ?array {
        $sql = "
            SELECT 
                cert.*,
                c.full_name AS candidate_name,
                c.father_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.batch,
                c.shift,
                c.branch,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                cmp.competition_date,
                cmp.venue,
                u.full_name AS issuer_name
            FROM certificates cert
            JOIN candidates c ON cert.candidate_id = c.id
            JOIN competitions cmp ON cert.competition_id = cmp.id
            LEFT JOIN users u ON cert.issued_by = u.id
            WHERE cert.id = ?
        ";

        return Database::fetch($sql, [$certId]);
    }
}
