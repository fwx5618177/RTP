<div align="center">
<h1 align="center">RTP</h1>

[![GitHub stars](https://img.shields.io/github/stars/fwx5618177/RTP.svg?style=social&label=Stars)](https://github.com/fwx5618177/RTP)
[![GitHub issues](https://img.shields.io/github/issues/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/issues)
[![GitHub license](https://img.shields.io/github/license/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/blob/main/LICENSE)
[![GitHub pull requests](https://img.shields.io/github/issues-pr/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/pulls)
[![GitHub contributors](https://img.shields.io/github/contributors/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/graphs/contributors)

[English](./README.md) | [简体中文](./README_ZH.md) | [日本語](./README_JP.md)

</div>

## プロジェクト概要

RTPは、PHPとSwooleをベースにした高性能なリアルタイム転送プロトコルブリッジバックエンドで、安定した効率的なリアルタイムデータ転送サービスを提供することを目的としています。

## WebSocket統合

本プロジェクトには以下の機能を備えたWebSocketサーバー実装が含まれています：

- **リアルタイム双方向通信**: サーバーとクライアント間のリアルタイムデータ交換を可能にします
- **高性能**: Swooleのイベント駆動型アーキテクチャを基盤としています
- **スケーラブル**: 最大1000同時接続をサポート
- **セキュア**: WebSocket接続のためのトークンベース認証
- **APIサーバーとの統合**: HTTP APIサーバーと並行して動作

### WebSocket設定

WebSocketサーバーは.envファイルで設定可能です：

```env
# WebSocket設定
WS_PORT=9502
WS_HOST=127.0.0.1
WS_PATH=/ws
WS_MAX_CONNECTIONS=1000
WS_TOKEN=test_token_123
```

### WebSocket使用方法

1. WebSocketサーバーを起動：

```bash
php src/index.php
```

2. WebSocketサーバーに接続：

```javascript
const ws = new WebSocket("ws://127.0.0.1:9502/ws");

ws.onopen = () => {
  console.log("WebSocketサーバーに接続しました");
  ws.send(
    JSON.stringify({
      token: "test_token_123",
      action: "subscribe",
      channel: "updates",
    })
  );
};

ws.onmessage = (message) => {
  console.log("受信:", message.data);
};
```

3. サーバーからメッセージを送信：

```php
$wsServer->sendToAll(json_encode([
  'event' => 'update',
  'data' => $payload
]));
```

4. クライアントメッセージを処理：

```php
$wsServer->on('message', function($frame) {
  // 受信メッセージを処理
  $data = json_decode($frame->data, true);

  // 異なるアクションを処理
  switch ($data['action']) {
    case 'subscribe':
      // クライアントをチャネルに追加
      break;
    case 'unsubscribe':
      // クライアントをチャネルから削除
      break;
    case 'message':
      // チャネルにメッセージをブロードキャスト
      break;
  }
});
```

### WebSocketテスト

提供されているテストファイルを使用してWebSocket機能をテストできます：

```bash
php tests/WebSocket/WebSocketTest.php
```

または、.httpテストファイルを使用：

```http
### WebSocketテスト
WEBSOCKET ws://127.0.0.1:9502/ws
Content-Type: application/json

{
  "token": "test_token_123",
  "action": "subscribe",
  "channel": "updates"
}
```

## 言語選択

本プロジェクトは以下の言語バージョンを提供しています：

- [English](README.md)
- [简体中文](README.zh.md)
- [日本語](README.ja.md)

## プロジェクトアーキテクチャ

本プロジェクトは階層型アーキテクチャを採用し、主な処理フローは以下の通りです：

1. Client -> Route: クライアントリクエストはまずルーティング層に入ります
2. Route -> Middleware: ルーティング層はリクエストパスに基づいてミドルウェアを照合します
3. Middleware -> Http: ミドルウェアはリクエストの前処理を行います
4. Http -> Controller: HTTP層はリクエストを解析しコントローラーに渡します
5. Controller -> DTO: コントローラーはリクエストデータをDTOオブジェクトに変換します
6. DTO -> Service: DTOオブジェクトはビジネスサービス層に渡されます
7. Service -> Entity: ビジネスサービス層はエンティティオブジェクトを操作します
8. Entity -> Repository: エンティティオブジェクトはリポジトリ層を通じて永続化されます
9. Repository -> DB: 最終的にデータベースにデータが保存されます

### アーキテクチャの使用方法

1. **ルート定義**

   - src/Routes/ディレクトリでルートを定義
   - Routeクラスを使用してルートを登録
   - GET/POST/PUT/DELETEなどのHTTPメソッドをサポート

2. **ミドルウェアの使用**

   - src/Middlewares/ディレクトリでミドルウェアを作成
   - MiddlewareInterfaceを実装
   - ルート定義時にmiddleware()メソッドで追加

3. **コントローラー開発**

   - src/Controllers/ディレクトリでコントローラーを作成
   - BaseControllerを継承
   - $requestオブジェクトでリクエストデータを取得
   - Responseオブジェクトを返す

4. **DTO変換**

   - src/DTO/ディレクトリでDTOクラスを定義
   - Validatorでデータ検証を実施
   - toArray()メソッドで配列に変換

5. **サービス層開発**

   - src/Services/ディレクトリでサービスクラスを作成
   - BaseServiceを継承
   - 依存性注入でRepositoryを使用

6. **エンティティとリポジトリ**
   - src/Entity/でエンティティクラスを定義
   - src/Repository/でリポジトリインターフェースを実装
   - DatabaseServiceProviderでリポジトリを登録

## TODO: 今後のアーキテクチャ改善計画

### アーキテクチャ移行

- [ ] 階層型アーキテクチャからDDD（ドメイン駆動設計）への移行
- [ ] ビジネスドメインによるモジュール分割
- [ ] 集約ルートと値オブジェクトの定義
- [ ] ドメインサービスの実装
- [ ] CQRSパターンの実装
- [ ] イベント駆動メカニズムの追加
- [ ] ドメインイベントの実装

### インフラストラクチャ

- [ ] メッセージキューサポートの追加（RabbitMQ/Kafka）
- [ ] 分散キャッシュの実装（Redis/Memcached）
- [ ] モニタリングとログトレース（Prometheus + Grafana）
- [ ] APIゲートウェイの実装
- [ ] サービスディスカバリーメカニズムの追加
- [ ] 自動スケーリングの実装
- [ ] ノードスナップショット機能の実装

### テスト改善

- [ ] 統合テストの追加
- [ ] 契約テストの実装
- [ ] パフォーマンステストの追加
- [ ] カオスエンジニアリングテストの実装
- [ ] セキュリティテストの追加

### 未実装機能

- [ ] ユーザー認証と認可（JWT/OAuth2）
- [ ] ファイルアップロードと保存
- [ ] データのページネーションとソート
- [ ] データエクスポート機能（CSV/Excel）
- [ ] スケジュールタスク管理
- [ ] メール通知サービス
- [ ] SMS認証サービス
- [ ] 第三者ログイン連携
- [ ] APIドキュメント自動生成
- [ ] データ移行ツール

## プロジェクト構造

```
.
├── config/               # 設定ファイル
│   ├── .env              # 環境変数
│   └── .env.sample       # 環境変数サンプル
├── database/             # データベース関連ファイル
│   ├── migrate.php       # データベース移行スクリプト
│   └── migrations/       # データベース移行ファイル
├── docs/                 # プロジェクトドキュメント
├── logs/                 # システムログファイル
├── scripts/              # デプロイメントと保守スクリプト
├── src/                  # コアコード
│   ├── Config/           # 設定クラス
│   │   ├── Config.php    # 設定管理
│   │   └── Routes.php    # ルート設定
│   ├── Controllers/      # コントローラー
│   ├── DTO/              # データ転送オブジェクト
│   ├── Entity/           # エンティティクラス
│   ├── Exceptions/       # カスタム例外
│   ├── Http/             # HTTP関連コンポーネント
│   │   ├── Request.php   # HTTPリクエスト処理
│   │   └── Response.php  # HTTPレスポンス処理
│   ├── Interfaces/       # インターフェース定義
│   │   ├── MiddlewareInterface.php  # ミドルウェアインターフェース
│   │   └── ModelInterface.php       # モデルインターフェース
│   ├── Logs/             # ログ処理
│   │   ├── Logger.php    # ログ記録
│   │   └── LogRotateService.php # ログローテーションサービス
│   ├── Middlewares/      # ミドルウェア
│   │   ├── MiddlewareStack.php      # ミドルウェアスタック
│   │   ├── TestConditionMiddleware.php # テスト条件ミドルウェア
│   │   └── TestFlowMiddleware.php   # テストフローミドルウェア
│   ├── Providers/        # サービスプロバイダー
│   │   └── DatabaseServiceProvider.php # データベースサービスプロバイダー
│   ├── Repository/       # データアクセス層
│   ├── Routes/           # ルート定義
│   │   ├── Route.php     # ルートクラス
│   │   └── Router.php    # ルーター
│   ├── Server/           # サーバー設定
│   │   └── ApiServer.php # APIサーバー
│   ├── Services/         # ビジネスロジック
│   ├── Utils/            # ユーティリティクラス
│   │   └── Container.php # 依存性注入コンテナ
│   └── Validator/        # データ検証
│       └── Validator.php # バリデーター
├── tests/                # テストコード
│   ├── http/             # HTTP APIテスト
│   │   ├── middleware-api.http # ミドルウェアAPIテスト
│   │   └── user-api.http       # ユーザーAPIテスト
│   └── Validator/        # バリデーターテスト
│       └── ValidatorTest.php
└── README.md             # プロジェクト説明
```

## ディレクトリの用途

- **config/**: プロジェクト設定ファイル、環境変数と設定クラスを含む
- **docs/**: プロジェクト関連ドキュメント
- **scripts/**: デプロイメントスクリプト、保守スクリプトなど
- **src/**: プロジェクトコアコード
  - **DTO/**: レイヤー間データ転送用のデータ転送オブジェクト
  - **Models/**: データモデル、データ構造とビジネスエンティティを定義
  - **Repositories/**: データアクセス層、データベース操作を担当
  - **Services/**: ビジネスロジック層、コアビジネスを処理
  - **Controllers/**: コントローラー、HTTPリクエストを処理
  - **Middlewares/**: ミドルウェア、リクエストの前処理とレスポンスの後処理
  - **Routes/**: ルート定義、URLをコントローラーにマッピング
  - **Utils/**: ユーティリティクラス、共通機能を提供
  - **Logs/**: ログ処理
  - **Exceptions/**: カスタム例外処理
  - **Interfaces/**: インターフェース定義
  - **Http/**: HTTP関連コンポーネント、リクエスト/レスポンス処理、フォーム検証など
  - **Server/**: サーバー関連設定
- **tests/**: 単体テストと機能テストコード

## コードチェックとフォーマット

プロジェクトはコード品質と一貫したスタイルを維持するために以下のツールを使用します：

- **PHP_CodeSniffer**: コードスタイルチェックと一般的なエラーの検出
- **PHP-CS-Fixer**: コードスタイルの問題を自動修正
- **PHPUnit**: 機能テストとエラー検出のための単体テストフレームワーク

注意：これらのツールは主にコードスタイルチェックとフォーマット用です。構文エラーを検出できますが、プロフェッショナルな静的コード解析ツールの代替にはなりません。

### ツールのインストール

```bash
composer require --dev squizlabs/php_codesniffer friendsofphp/php-cs-fixer
```

### 使用方法

1. コードスタイルチェック：

```bash
./vendor/bin/phpcs
```

2. コードスタイル自動修正：

```bash
./vendor/bin/phpcbf
```

3. PHP-CS-Fixerでコードフォーマット：

```bash
PHP_CS_FIXER_IGNORE_ENV=1 ./vendor/bin/php-cs-fixer fix
```

注意：現在のPHPバージョン（8.4.3）はPHP-CS-Fixerがサポートする最高バージョン（8.3.\*）より高いため、バージョンチェックを無視するためにPHP_CS_FIXER_IGNORE_ENV環境変数を設定する必要があります。

### 設定

- `phpcs.xml`: PHP_CodeSniffer設定ファイル
- `.php-cs-fixer.php`: PHP-CS-Fixer設定ファイル

## 貢献ガイドライン

あらゆる形の貢献を歓迎します！貢献を始める前に、以下のガイドラインをお読みください。

### 開発プロセス

1. プロジェクトリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. 変更をコミット (`git commit -m 'Add some AmazingFeature'`)
4. ブランチにプッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを提出

### コードスタイル

- PSR-12コーディング規約に従う
- PHP_CodeSnifferでコードスタイルをチェック
- PHP-CS-Fixerで自動的にコードをフォーマット
- クラス名はアッパーキャメルケース (UpperCamelCase)
- メソッド名はローワーキャメルケース (lowerCamelCase)
- 定数名は大文字とアンダースコア (UPPER_CASE)
- 変数名はローワーキャメルケース (lowerCamelCase)

### テスト要件

- すべての新機能には単体テストを含める
- バグ修正には回帰テストを含める
- テストカバレッジは80%以上を維持
- `.http`ファイルでAPIテストを実施
- 推奨ツール：
  - **REST Client** (VSCodeプラグイン)
    - インストール：VSCode拡張マーケットプレイスで"REST Client"を検索してインストール
    - 使用方法：`.http`ファイルを直接開き、"Send Request"ボタンをクリックしてテストを実行
  - **Postman**
    - `.http`ファイルをインポートしてテスト
    - より複雑なテストシナリオと自動化テストをサポート

### テストの実行

```bash
# すべてのテストを実行
./vendor/bin/phpunit

# 特定のテストファイルを実行
./vendor/bin/phpunit tests/Validator/ValidatorTest.php
```

### プルリクエストガイドライン

- タイトル形式：[タイプ] 簡単な説明
  - 例：[Feature] ユーザー認証機能の追加
  - タイプには：Feature, Bugfix, Refactor, Docs, Style, Testが含まれます
- 説明には以下を含める：
  - 解決した問題または実装した機能
  - テスト結果
  - 関連するissue番号（ある場合）
  - 重大な変更の説明（ある場合）
- すべてのテストが通過することを確認
- コードスタイルが規約に準拠していることを確認
- ドキュメントが更新されていることを確認

## インストールと設定

### 要件

- PHP 8.4.3以上
- Composer 2.0以上
- Swoole 6.0.0以上
- MySQL 8.0以上
- Redis 6.0以上（オプション）

### インストール手順

1. プロジェクトのクローン：

```bash
git clone https://github.com/fwx5618177/rtp.git
cd rtp
```

2. 依存関係のインストール：

```bash
composer install
```

3. Swoole拡張のインストール：

```bash
pecl install swoole
```

注意：SwooleにはPHPがZTS（Zend Thread Safety）有効でコンパイルされている必要があります。インストールに問題が発生した場合は以下を確認してください：

- PHPが--enable-ztsパラメータでコンパイルされていること
- php -i | grep Threadの出力に"Thread Safety => enabled"が含まれていること
- brewでインストールしたPHPを使用している場合は、brew install php --with-ztsで再インストールすることを推奨

4. Swoole拡張の有効化：

```bash
echo "extension=swoole.so" >> $(php -i | grep "Loaded Configuration File" | awk '{print $5}')
```

5. 環境変数の設定：

```bash
cp config/.env.sample config/.env
```

config/.envファイルを編集し、以下を設定：

```env
# アプリケーション設定
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:your_app_key

# データベース設定
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rtp
DB_USERNAME=root
DB_PASSWORD=

# Redis設定（オプション）
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Swoole設定
SWOOLE_HOST=0.0.0.0
SWOOLE_PORT=9501
SWOOLE_WORKER_NUM=4
SWOOLE_TASK_WORKER_NUM=2
```

6. アプリケーションキーの生成：

```bash
php artisan key:generate
```

7. データベースマイグレーションの実行：

```bash
php database/migrate.php
```

8. 開発サーバーの起動：

```bash
php src/index.php
```

### デフォルト設定

- リッスンアドレス：0.0.0.0
- リッスンポート：9501
- アクセスアドレス：http://localhost:9501

## コミュニティガイドライン

- [行動規範](CODE_OF_CONDUCT.md) - コミュニティの行動基準
- [セキュリティポリシー](SECURITY.md) - セキュリティ問題の報告方法

## ライセンス

本プロジェクトは[MITライセンス](LICENSE)の下で公開されています。

```text
MIT License

Copyright (c) 2023 fwx5618177 <fwx5618177@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
