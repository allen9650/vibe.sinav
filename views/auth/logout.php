<?php
/**
 * Logout Handler
 */
Auth::logout();
Session::start(); // Restart for flash message
Session::flash('success', 'You have been logged out successfully.');
redirect(url('login'));
