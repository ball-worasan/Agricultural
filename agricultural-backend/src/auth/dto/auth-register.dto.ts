import {
  IsEmail,
  IsOptional,
  IsString,
  MinLength,
  Matches,
} from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class AuthRegisterDto {
  @ApiProperty({ example: 'Thanaporn Worasan' })
  @IsString()
  @MinLength(2)
  fullName!: string;

  @ApiProperty({ example: 'ball@example.com' })
  @IsEmail()
  email!: string;

  @ApiPropertyOptional({ example: '99/1 Sukhumvit Rd., BKK' })
  @IsOptional()
  @IsString()
  address?: string;

  @ApiPropertyOptional({ description: 'Digits 8–15', example: '0812345678' })
  @IsOptional()
  @Matches(/^\d{8,15}$/)
  contactPhone?: string;

  @ApiProperty({ example: 'ball.admin' })
  @IsString()
  @MinLength(3)
  username!: string;

  @ApiProperty({ minLength: 8, example: 'P@ssw0rd_123' })
  @IsString()
  @MinLength(8)
  password!: string;
}
