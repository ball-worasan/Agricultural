import { Injectable, Logger } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { ExtractJwt, Strategy } from 'passport-jwt';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  private readonly logger = new Logger(JwtStrategy.name);

  constructor(cfg: ConfigService) {
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey: cfg.get<string>('auth.jwtSecret', 'change-me'),
    });
    this.logger.log('JwtStrategy initialized');
  }

  async validate(payload: any) {
    // ไทย: ค่าที่จะไปอยู่ใน req.user
    this.logger.debug(`jwt validate (sub=${payload?.sub})`);
    return { sub: payload.sub, username: payload.username, email: payload.email };
  }
}
