// Initialize Firebase
let auth;
let provider;

function initializeFirebase(firebaseConfig) {
    firebase.initializeApp(firebaseConfig);
    auth = firebase.auth();
    
    // Configure Google Sign-In
    provider = new firebase.auth.GoogleAuthProvider();
    provider.addScope('email');
    provider.addScope('profile');
}

// Show/hide loading spinner
function showLoading() {
    const spinner = document.getElementById('loading-spinner');
    const btn = document.getElementById('googleSignInBtn');
    
    if (spinner) spinner.style.display = 'block';
    if (btn) btn.disabled = true;
}

function hideLoading() {
    const spinner = document.getElementById('loading-spinner');
    const btn = document.getElementById('googleSignInBtn');
    
    if (spinner) spinner.style.display = 'none';
    if (btn) btn.disabled = false;
}

// Handle Google Sign-In
async function handleGoogleSignIn() {
    showLoading();
    
    try {
        const result = await auth.signInWithPopup(provider);
        const user = result.user;
        const idToken = await user.getIdToken();
        
        // Send token to PHP backend
        const formData = new FormData();
        formData.append('firebase_token_login', '1');
        formData.append('id_token', idToken);
        formData.append('email', user.email);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const responseData = await response.json();
        
        if (responseData.success) {
            window.location.reload();
        } else {
            throw new Error(responseData.error || 'Authentication failed');
        }
    } catch (error) {
        console.error('Google Sign-In Error:', error);
        alert('Google Sign-In failed: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Handle form submission errors
function setupFormHandlers() {
    const authForm = document.getElementById('authForm');
    if (authForm) {
        authForm.addEventListener('submit', function() {
            showLoading();
        });
    }
    
    const googleSignInBtn = document.getElementById('googleSignInBtn');
    if (googleSignInBtn) {
        googleSignInBtn.addEventListener('click', handleGoogleSignIn);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupFormHandlers();
}); 