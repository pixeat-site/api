# PixEat - Downloads

## 📱 Versões Disponíveis

### 🚀 v1.2.0 (ATUAL) - 14 de Outubro 2025
**Arquivo:** `pixeat-v1.2.0.apk` (21MB)

**🔥 Correções Críticas:**
- ✅ **Análise de IA muito mais precisa** - Prompts otimizados para Gemini AI
- ✅ **Dashboard atualiza automaticamente** após tirar foto e salvar refeição
- ✅ **Fotos aparecem no histórico** - Mapeamento corrigido para `food_name` e `meal_type`
- ✅ **Interface otimizada** - Botões sempre visíveis (SafeArea implementado)
- ✅ **APK 60% menor** - Otimizações de build (21MB vs 55MB)
- ✅ **Logs detalhados** para debug e monitoramento

**Problemas Resolvidos:**
- ❌ Foto não aparecia no histórico
- ❌ Botão "Analisar" encoberto na tela de confirmação  
- ❌ Dashboard não atualizava após salvar refeição
- ❌ Análise da IA imprecisa

---

### v1.1.0 (ANTERIOR) - 05 de Outubro 2025
**Arquivo:** `pixeat-v1.1.0.apk` (55MB)

Versão anterior com funcionalidades básicas.

---

## 🔗 Links de Download

- **Atual:** https://api.pixeat.com.br/downloads/pixeat-v1.2.0.apk
- **Anterior:** https://api.pixeat.com.br/downloads/pixeat-v1.1.0.apk

## 📋 Requisitos

- Android 5.0+ (API Level 21)
- 50MB de espaço livre
- Câmera (opcional - pode usar galeria)
- Internet para login e análise de IA

## 🧪 Como Testar

1. Baixar e instalar APK v1.2.0
2. Fazer login/cadastro
3. Tirar foto de comida
4. Verificar análise precisa da IA
5. Confirmar refeição
6. **VERIFICAR:** Dashboard atualiza automaticamente
7. **VERIFICAR:** Foto aparece no histórico

## 🛠️ Para Desenvolvedores

Para gerar novo APK:

```bash
cd flutter/
flutter clean
flutter pub get
flutter build apk --release --target-platform android-arm64
cp build/app/outputs/flutter-apk/app-release.apk ../api/src/public/downloads/pixeat-vX.X.X.apk
```

O APK será disponibilizado em: `https://api.pixeat.com.br/downloads/pixeat-vX.X.X.apk`