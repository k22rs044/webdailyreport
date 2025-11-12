FROM php:8.3-apache

# 拡張モジュールをインストール (mysqli)
RUN docker-php-ext-install mysqli 

# --- Composerと依存パッケージのインストール ---
# curl, unzip, libssl-dev をインストール
RUN apt-get update && \
    apt-get install -y \
        curl \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# Composerのインストール（グローバルに使えるように /usr/local/bin に配置）
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# --- PHPMailerの準備とインストール ---

# アプリケーションの作業ディレクトリに移動
WORKDIR /var/www/html

# ホスト側の composer.json をコンテナ内の /var/www/html にコピーする
COPY container/htdocs/composer.json .

# 既存の composer.json に基づいて依存関係をインストールする
RUN composer install --no-dev --optimize-autoloader