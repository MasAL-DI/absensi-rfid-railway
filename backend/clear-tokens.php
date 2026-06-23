// Clear old tokens and refresh
console.log('Clearing old tokens...');
localStorage.removeItem('rfid_token');
localStorage.removeItem('rfid_user');
localStorage.removeItem('rfid_username');
localStorage.removeItem('rfid_role');
console.log('✓ Old tokens cleared');
console.log('Please refresh the page and login again');
location.reload();
