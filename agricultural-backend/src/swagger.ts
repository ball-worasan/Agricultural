import { INestApplication } from '@nestjs/common';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';

export function setupSwagger(app: INestApplication) {
  const config = new DocumentBuilder()
    .setTitle('Agricultural Rental API')
    .setDescription('REST API documentation for Auth & Users and more')
    .setVersion('1.0.0')
    .addBearerAuth(
      {
        type: 'http',
        scheme: 'bearer',
        bearerFormat: 'JWT',
        description: 'Paste your JWT here',
      },
      'bearer', // name of the security scheme
    )
    .build();

  const doc = SwaggerModule.createDocument(app, config, {
    deepScanRoutes: true, // สแกนทุกโมดูล/คอนโทรลเลอร์
  });

  // UI path: /docs  |  JSON path: /docs-json
  SwaggerModule.setup('docs', app, doc, {
    swaggerOptions: {
      persistAuthorization: true,
      displayRequestDuration: true,
      tryItOutEnabled: true,
    },
    customSiteTitle: 'Agricultural API Docs',
  });
}
