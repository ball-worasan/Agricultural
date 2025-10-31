import { IsString, MinLength } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class LoginDto {
  @ApiProperty({
    description: 'Username or Email',
    example: 'ball.admin',
  })
  @IsString()
  identifier!: string;

  @ApiProperty({
    minLength: 8,
    example: 'P@ssw0rd_123',
  })
  @IsString()
  @MinLength(8)
  password!: string;
}
