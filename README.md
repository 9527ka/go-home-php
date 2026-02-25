# 回家了么 (Go Home)

**帮助每一个走失的生命回家**

一个开源的公益寻人平台，用于发布和搜索走失成年人、儿童、宠物等寻人寻物启事。本项目绝大部分代码由 **Claude AI** 辅助编写，是一次 AI 驱动开发的公益实践。

> 哪怕只帮助一个人找到回家的路，这个项目就有意义。

---

## 主要功能

- **发布寻人/寻物启事** — 支持走失成年人、儿童、宠物、物品等分类，可上传照片、填写特征描述和走失地点
- **浏览与搜索** — 按分类、城市、关键词、日期筛选，快速定位相关启事
- **线索提交** — 发现疑似走失者可在线提交线索，系统自动通知发布者
- **实时聊天室** — 支持文字、图片、语音、视频，方便志愿者实时沟通协作
- **消息通知** — 线索回复、审核结果等实时推送
- **内容审核** — 所有启事经审核后展示，保障信息真实性
- **儿童保护** — 儿童类启事禁止暴露精确地址
- **多语言** — 中文 / English

## 技术栈

| 端 | 技术 |
|------|------|
| **客户端** | Flutter 3.x / Provider / Dio / WebSocket |
| **后端** | PHP 8.0 + ThinkPHP 6.1 |
| **数据库** | MySQL 8.0 |
| **实时通信** | Workerman (WebSocket) |
| **认证** | JWT / Apple Sign-In |

## 项目结构

```
go-home/
├── flutter_app/          # Flutter 客户端（iOS / Android / Web）
│   ├── lib/
│   │   ├── pages/        # 页面（首页、发布、详情、聊天、个人中心等）
│   │   ├── models/       # 数据模型
│   │   ├── services/     # API 服务层
│   │   ├── providers/    # 状态管理
│   │   ├── widgets/      # 通用组件
│   │   └── l10n/         # 国际化
│   └── pubspec.yaml
│
└── server/               # PHP 后端 API
    ├── app/
    │   ├── api/          # 客户端 API（认证、启事、线索、聊天、通知等）
    │   ├── admin/        # 管理后台 API（审核、统计、用户管理等）
    │   └── common/       # 共享模型、服务、枚举
    ├── database/         # 数据库迁移文件
    ├── worker/           # WebSocket 聊天服务
    └── composer.json
```

## 快速开始

### 后端

```bash
cd server

# 安装依赖
composer install

# 配置环境变量
cp .env.example .env
# 编辑 .env，填入数据库连接、JWT 密钥等配置

# 初始化数据库（依次执行迁移脚本）
mysql -u root -p your_database < database/migrations/001_create_all_tables.sql
mysql -u root -p your_database < database/migrations/002_add_category_other.sql
mysql -u root -p your_database < database/migrations/003_add_apple_signin.sql
mysql -u root -p your_database < database/migrations/004_add_feedbacks_and_chat.sql
mysql -u root -p your_database < database/migrations/005_chat_media_support.sql

# 启动 WebSocket 聊天服务
php worker/ChatServer.php start -d

# 配置 Nginx（参考 nginx.conf.example）
```

### 客户端

```bash
cd flutter_app

# 安装依赖
flutter pub get

# 修改 API 地址（lib/config/api.dart）

# 运行
flutter run
```

## 如何参与贡献

这是一个**纯公益项目**，欢迎每一位开发者贡献力量！无论是修复一个 Bug、优化一处体验，还是新增一个功能，每一份贡献都有意义。

### 参与方式

1. **Fork** 本仓库
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交修改：`git commit -m "feat: 添加某某功能"`
4. 推送到远程：`git push origin feature/your-feature`
5. 提交 **Pull Request**

### 当前需要帮助的方向

- [ ] 地图集成 — 在启事中展示走失地点地图
- [ ] 推送通知 — 接入 FCM / APNs 实现离线推送
- [ ] 附近的人 — 基于定位展示附近的寻人启事
- [ ] 数据统计 — 找回成功率等数据可视化
- [ ] Android 适配 — 完善 Android 端的兼容和体验
- [ ] 无障碍优化 — 提升 Accessibility 支持
- [ ] 单元测试 — 补充前后端测试覆盖率
- [ ] UI/UX 优化 — 提升界面设计和交互体验
- [ ] 管理后台前端 — 开发 Web 管理后台界面
- [ ] 性能优化 — 列表加载、图片压缩等优化

欢迎在 [Issues](../../issues) 中提出建议或认领任务。

## 关于 AI 辅助开发

本项目是一次 **AI 辅助开发公益产品**的探索——从后端 API 到 Flutter 客户端，绝大部分代码由 [Claude AI](https://claude.ai) 辅助生成。这证明了借助 AI 的力量，个人开发者也能快速构建一个有社会价值的完整产品。

我们希望通过开源，让更多开发者参与进来，用技术的力量帮助走失的人找到回家的路。

## License

MIT License - 详见 [LICENSE](LICENSE) 文件
