# Utiliser l'image de base de FrankenPHP
FROM dunglas/frankenphp

# GIT
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    curl \
    wget \
    vim

# Étape supplémentaire : installer Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Bash
RUN echo "export HISTFILE=/root/.bash_history" >> /root/.bashrc

# Ajout de la configuration de répertoire sûr pour Git
RUN git config --global --add safe.directory /app/web

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Remplacez YOUR_UID et YOUR_GID par les valeurs obtenues avec les commandes id -u et id -g
ARG USER_UID=1000
ARG USER_GID=1000

# Création de l'utilisateur local dans le conteneur
RUN groupadd -g $USER_GID localuser \
    && useradd -m -u $USER_UID -g $USER_GID -s /bin/bash localuser \
    && chown -R localuser:localuser /app

# Met à jour les packages et ajoute les dépendances nécessaires
RUN apt-get update && apt-get install -y apt-transport-https curl gnupg

# Installer les extensions nécessaires, y compris pdo_mysql et dom
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql calendar dom

# Définir le dossier de travail
WORKDIR /app/web

# Exposer le port 443 pour HTTPS
EXPOSE 443
