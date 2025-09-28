import { IsString, MinLength } from 'class-validator';

export class LoginDto {
  @IsString()
  identifier!: string; // ไทย: email หรือ username

  @IsString()
  @MinLength(8)
  password!: string;
}
