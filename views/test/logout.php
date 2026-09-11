<?php
/**
 * Candidate Test Portal — Logout Handler
 */

Session::remove('candidate_logged_in');
Session::remove('candidate_id');
Session::remove('candidate_name');
Session::remove('candidate_roll');
Session::remove('candidate_reg');
Session::remove('candidate_comp_id');
Session::remove('current_attempt_id');

redirectTo('test-portal');
