# Mapping des clés de traduction FR → EN

## Authentification / Login

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Connexion | Sign In |
| Connexion en cours... | Signing in... |
| Se connecter | Sign In |
| Se connecter à mon compte | Sign in to my account |
| Se souvenir de moi | Remember me |
| Déconnexion | Logout |
| Mot de passe | Password |
| Mot de passe oublié ? | Forgot password? |
| Adresse email | Email address |
| votre@email.com | your@email.com |
| Entrez vos identifiants pour accéder à votre compte | Enter your credentials to access your account |
| L'email est requis | Email is required |
| Le mot de passe est requis | Password is required |
| L'adresse email est requise | Email address is required |
| Vous vous souvenez de votre mot de passe ? | Remember your password? |
| Retour à la connexion | Back to sign in |

## Inscription / Register

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Créer un compte | Create an account |
| Créer mon compte | Create my account |
| Création en cours... | Creating account... |
| Compte créé avec succès ! Redirection... | Account created successfully! Redirecting... |
| Votre compte a été créé avec succès. Veuillez vous connecter. | Your account was created successfully. Please sign in. |
| Remplissez vos informations pour commencer | Fill in your information to get started |
| Prénom | First Name |
| Nom | Last Name |
| Le prénom est requis | First name is required |
| Le nom est requis | Last name is required |
| Le mot de passe doit contenir au moins 8 caractères | Password must be at least 8 characters |
| Minimum 8 caractères | Minimum 8 characters |
| Déjà membre ? | Already a member? |
| Déjà un compte ? Connectez-vous | Already have an account? Sign in |
| Nouveau ici ? | New here? |
| Rejoignez | Join |
| Je souhaite recevoir les actualités et offres exclusives par email | I want to receive news and exclusive offers by email |

## Mot de passe oublié

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Envoyer le nouveau mot de passe | Send new password |
| Envoi en cours... | Sending... |
| Renvoi en cours... | Resending... |
| Entrez votre adresse email et nous vous enverrons un nouveau mot de passe. | Enter your email address and we will send you a new password. |
| Si votre adresse email existe dans notre base de données, vous recevrez un lien de récupération de mot de passe dans quelques minutes. | If your email address exists in our database, you will receive a password recovery link in a few minutes. |
| Pas d'inquiétude, cela arrive. Nous allons vous aider à récupérer l'accès à votre compte. | Don't worry, it happens. We'll help you recover access to your account. |
| Un nouvel email a été envoyé. Veuillez vérifier votre boîte de réception. | A new email has been sent. Please check your inbox. |
| Vous n'avez pas reçu l'email ? Renvoyer | Didn't receive the email? Resend |
| Veuillez entrer une adresse email valide | Please enter a valid email address |

## Navigation / Menu

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Accueil | Home |
| Bienvenue sur | Welcome to |
| Mon profil | My Profile |
| Mes commandes | My Orders |
| Mes adresses | My Addresses |
| Mes favoris | My Wishlist |
| Ma liste de souhaits | My Wishlist |
| Favoris | Wishlist |

## Panier / Cart

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Ajouter au panier | Add to cart |
| Ajout en cours... | Adding... |
| Continuer mes achats | Continue shopping |
| Commencer mes achats | Start shopping |
| Total TTC | Total (incl. tax) |
| Subtotal HT | Subtotal (excl. tax) |

## Favoris / Wishlist

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Ajouter aux favoris | Add to wishlist |
| Retirer des favoris | Remove from wishlist |
| Votre liste de favoris est vide | Your wishlist is empty |
| Ajoutez vos premiers favoris | Add your first favorites |
| Consultez et gérez vos produits favoris | View and manage your favorite products |
| Sauvegardez vos favoris | Save your favorites |
| Synchronisez vos favoris sur tous vos appareils | Sync your favorites across all your devices |
| Votre liste actuelle est sauvegardée localement dans votre navigateur. Créez un compte pour ne jamais la perdre ! | Your current list is saved locally in your browser. Create an account to never lose it! |
| Tout supprimer | Remove all |

## Compte / Account

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Passez commande plus rapidement | Order faster |
| Suivez vos commandes facilement | Track your orders easily |
| Soyez alerté des promotions | Get notified about promotions |
| Offres exclusives membres | Exclusive member offers |
| Paiement rapide | Fast checkout |

## Légal

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Conditions générales | Terms and Conditions |
| Politique de confidentialité | Privacy Policy |
| Vous devez accepter les conditions générales | You must accept the terms and conditions |
| En vous connectant, vous acceptez nos | By signing in, you accept our |
| et notre | and our |
| et la | and the |

## Erreurs

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Une erreur s'est produite. Veuillez réessayer. | An error occurred. Please try again. |

## Exemples / Placeholders

| Clé française actuelle | Nouvelle clé anglaise |
|------------------------|----------------------|
| Jean | John |
| Dupont | Doe |
| +33 6 12 34 56 78 | +1 555 123 4567 |

---

## Fichiers Twig à modifier

Les fichiers suivants contiennent des clés françaises :
- login.html.twig
- register.html.twig
- password-forgotten.html.twig
- account.html.twig
- account-*.html.twig
- wishlist.html.twig
- components/Layout/Header.html.twig
- Et autres...

## Étapes suivantes

1. [ ] Mettre à jour chaque fichier Twig avec les clés anglaises
2. [ ] Nettoyer messages.fr_FR.yaml (supprimer les clés françaises, garder uniquement EN: FR)
3. [ ] Vérifier que toutes les traductions fonctionnent
